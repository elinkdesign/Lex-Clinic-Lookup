<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordController extends Controller
{
    private const LONG_LIST_TABLE = '[Corepoint].[dbo].[Provider Legacy ID and NPI - Long List]';
    private const SHORT_LIST_TABLE = '[Corepoint].[dbo].[Provider Legacy ID and NPI - Short List]';
    private const LOG_TABLE = 'cpdr_form_log';
    private const SQL_CONNECTION = 'corepoint'; // see step 2

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);

        $legacyId = $this->sanitizeLegacyId($payload['legacy_id']);
        $npi = $this->sanitizeNpi($payload['npi']);
        $lineDescription = trim($payload['line_description']);
        $deleteExisting = $request->boolean('delete_existing_records');

        [$longMatches, $shortMatches] = $this->findExistingRecords($legacyId, $npi);

        if (($longMatches->isNotEmpty() || $shortMatches->isNotEmpty()) && !$deleteExisting) {
            return response()->json([
                'success' => false,
                'message' => 'Matching records already exist.',
                'duplicates' => [
                    'longList' => $longMatches,
                    'shortList' => $shortMatches,
                ],
            ], 409);
        }

        DB::connection(self::SQL_CONNECTION)->transaction(function () use (
            $legacyId,
            $npi,
            $lineDescription,
            $deleteExisting,
            $longMatches,
            $shortMatches
        ) {
            if ($deleteExisting && ($longMatches->isNotEmpty() || $shortMatches->isNotEmpty())) {
                $this->deleteExistingRecords($legacyId, $npi);
                $this->logAction('DELETE_ACTION', $legacyId, $npi, $lineDescription);
            }

            $this->insertRecords($legacyId, $npi, $lineDescription);
            $this->logAction('CREATE_ACTION', $legacyId, $npi, $lineDescription);
        });

        return response()->json([
            'success' => true,
            'message' => 'Your form submission was successful!',
        ]);
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'max:255'],
            'searchType' => ['nullable', 'in:legacy_id,npi'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $query = trim($request->input('query'));
        $searchType = $request->input('searchType', 'legacy_id');

        $column = $searchType === 'legacy_id' ? '[Provider Legacy ID]' : '[Provider NPI]';

        $result = $this->lookupByColumn(self::LONG_LIST_TABLE, $column, $query);

        return response()->json([
            'success' => true,
            'match' => $result ?: null,
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'legacy_id' => ['required', 'string', 'max:50', 'min:3'],
                'npi' => ['required', 'string', 'size:10', 'regex:/^\d+$/'],
                'line_description' => ['required', 'string', 'max:255'],
                'delete_existing_records' => ['nullable', 'boolean'],
            ],
            [
                'legacy_id.min' => 'Please provide at least 3 characters for Legacy ID.',
                'npi.size' => 'Please provide a 10 digit NPI.',
                'npi.regex' => 'NPI may only contain digits.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function sanitizeLegacyId(string $legacyId): string
    {
        return strtoupper(str_replace('_', '', trim($legacyId)));
    }

    private function sanitizeNpi(string $npi): string
    {
        return preg_replace('/\D/', '', $npi);
    }

    private function findExistingRecords(string $legacyId, string $npi): array
    {
        $longMatches = $this->lookupByIds(self::LONG_LIST_TABLE, $legacyId, $npi);
        $shortMatches = $this->lookupByIds(self::SHORT_LIST_TABLE, $legacyId, $npi);

        return [$longMatches, $shortMatches];
    }

    private function lookupByIds(string $table, string $legacyId, string $npi)
    {
        return DB::connection(self::SQL_CONNECTION)
            ->table(DB::raw($table))
            ->select('*')
            ->where('[Provider Legacy ID]', $legacyId)
            ->orWhere('[Provider NPI]', $npi)
            ->get();
    }

    private function insertRecords(string $legacyId, string $npi, string $lineDescription): void
    {
        $isNumeric = ctype_digit($legacyId);
        $isFourDigitNumeric = $isNumeric && strlen($legacyId) === 4;
        $containsLetters = preg_match('/[A-Za-z]/', $legacyId);

        $this->insertRow(self::LONG_LIST_TABLE, $legacyId, $npi, $lineDescription);

        if ($isNumeric && !$isFourDigitNumeric) {
            $this->insertRow(self::SHORT_LIST_TABLE, $legacyId, $npi, $lineDescription);
        } elseif (!$isNumeric && !$containsLetters) {
            $this->insertRow(self::SHORT_LIST_TABLE, $legacyId, $npi, $lineDescription);
        }
    }

    private function insertRow(string $table, string $legacyId, string $npi, string $lineDescription): void
    {
        DB::connection(self::SQL_CONNECTION)
            ->table(DB::raw($table))
            ->insert([
                '[Provider Legacy ID]' => $legacyId,
                '[Provider NPI]' => $npi,
                '[Line Description]' => $lineDescription,
            ]);
    }

    private function deleteExistingRecords(string $legacyId, string $npi): void
    {
        foreach ([self::LONG_LIST_TABLE, self::SHORT_LIST_TABLE] as $table) {
            DB::connection(self::SQL_CONNECTION)
                ->table(DB::raw($table))
                ->where('[Provider Legacy ID]', $legacyId)
                ->orWhere('[Provider NPI]', $npi)
                ->delete();
        }
    }

    private function logAction(string $action, string $legacyId, string $npi, string $lineDescription): void
    {
        $user = optional(auth()->user())->name ?? optional(auth()->user())->username ?? 'system';
        $timestamp = now()->format('Y-m-d H:i:s');

        DB::connection(self::SQL_CONNECTION)
            ->table(self::LOG_TABLE)
            ->insert([
                'user_name' => $user,
                'legacy_id' => $legacyId,
                'npi' => $npi,
                'line_description' => $lineDescription,
                'submit_date' => $timestamp,
                'action' => $action,
            ]);
    }

    private function lookupByColumn(string $table, string $column, string $value)
    {
        return DB::connection(self::SQL_CONNECTION)
            ->table(DB::raw($table))
            ->select('*')
            ->where(DB::raw("LTRIM(RTRIM({$column}))"), $value)
            ->first();
    }
}