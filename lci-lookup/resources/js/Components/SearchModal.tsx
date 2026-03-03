import axios from 'axios';
import { useState } from 'react';

interface SearchModalProps {
    isOpen: boolean;
    onClose: () => void;
}

type SearchType = 'legacy_id' | 'npi';

interface MatchRecord {
    'Provider Legacy ID': string;
    'Provider NPI': string;
    'Line Description': string;
}

export default function SearchModal({ isOpen, onClose }: SearchModalProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [searchType, setSearchType] = useState<SearchType>('legacy_id');
    const [match, setMatch] = useState<MatchRecord | null>(null);
    const [searching, setSearching] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [hasSearched, setHasSearched] = useState(false);

    const resetState = () => {
        setSearchTerm('');
        setSearchType('legacy_id');
        setMatch(null);
        setError(null);
        setNotice(null);
        setSearching(false);
        setDeleting(false);
        setHasSearched(false);
    };

    const handleClose = () => {
        resetState();
        onClose();
    };

    const lookup = async () => {
        setError(null);
        setNotice(null);
        setMatch(null);
        setSearching(true);

        try {
            const response = await axios.post('/search', {
                query: searchTerm,
                searchType,
            });

            setMatch(response.data.match ?? null);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                const message =
                    err.response.data?.errors?.query?.join(' ') ||
                    'Validation error. Please check your input.';
                setError(message);
            } else {
                setError('Unable to complete the lookup. Please try again.');
            }
        } finally {
            setSearching(false);
            setHasSearched(true);
        }
    };

    const deleteMatch = async () => {
        if (!match || deleting) {
            return;
        }

        const confirmed = window.confirm(
            `Are you sure you want to delete ${match['Provider Legacy ID']}?`,
        );

        if (!confirmed) {
            return;
        }

        setError(null);
        setNotice(null);
        setDeleting(true);

        try {
            const response = await axios.delete('/delete-record', {
                data: {
                    legacy_id: match['Provider Legacy ID'],
                    npi: match['Provider NPI'],
                    line_description: match['Line Description'],
                },
            });

            setMatch(null);
            setSearchTerm('');
            setHasSearched(false);
            setNotice(
                response.data?.message ?? 'Item was successfully deleted.',
            );
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.status === 404) {
                setError(
                    err.response.data?.message ??
                        'No matching records were found to delete.',
                );
            } else if (
                axios.isAxiosError(err) &&
                err.response?.status === 422
            ) {
                setError(
                    'Delete validation failed. Please run the lookup again.',
                );
            } else {
                setError(
                    'Unable to delete the record right now. Please try again.',
                );
            }
        } finally {
            setDeleting(false);
        }
    };

    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div className="w-full max-w-lg rounded bg-white shadow-lg">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h2 className="text-lg font-semibold">Lookup Tool</h2>
                    <button
                        className="text-gray-500 hover:text-gray-700"
                        onClick={handleClose}
                    >
                        ×
                    </button>
                </div>

                <div className="px-4 py-5">
                    <p className="mb-4 text-sm text-gray-600">
                        Search for a Legacy ID or NPI to find existing records.
                    </p>

                    <div className="mb-4 flex gap-3">
                        <select
                            className="rounded border border-gray-300 px-2 py-2 pr-8"
                            value={searchType}
                            onChange={(e) => {
                                setSearchType(e.target.value as SearchType);
                                setHasSearched(false);
                            }}
                        >
                            <option value="legacy_id">Legacy ID</option>
                            <option value="npi">NPI</option>
                        </select>

                        <input
                            type="text"
                            className="flex-1 rounded border border-gray-300 px-3 py-2"
                            value={searchTerm}
                            onChange={(e) => {
                                setSearchTerm(e.target.value);
                                setHasSearched(false);
                            }}
                            placeholder="Enter search term"
                        />
                        <button
                            className="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                            onClick={lookup}
                            disabled={searchTerm.length === 0 || searching}
                        >
                            {searching ? 'Searching…' : 'Lookup'}
                        </button>
                    </div>

                    {error && (
                        <div className="mb-4 rounded border border-red-400 bg-red-50 px-3 py-2 text-sm text-red-700">
                            {error}
                        </div>
                    )}
                    {notice && (
                        <div className="mb-4 rounded border border-green-400 bg-green-50 px-3 py-2 text-sm text-green-700">
                            {notice}
                        </div>
                    )}

                    <div>
                        {match && (
                            <div className="rounded border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-800">
                                <p>
                                    <strong>Legacy ID:</strong>{' '}
                                    {match['Provider Legacy ID']}
                                </p>
                                <p>
                                    <strong>NPI:</strong>{' '}
                                    {match['Provider NPI']}
                                </p>
                                <p>
                                    <strong>Line Description:</strong>{' '}
                                    {match['Line Description']}
                                </p>
                                <div className="mt-4 text-center">
                                    <button
                                        type="button"
                                        className="rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50"
                                        onClick={deleteMatch}
                                        disabled={deleting}
                                    >
                                        {deleting ? 'Deleting...' : 'Delete'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {!match && !error && hasSearched && !searching && (
                            <p className="text-sm text-gray-600">
                                No results found.
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex justify-end border-t px-4 py-3">
                    <button
                        className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
                        onClick={handleClose}
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}
