import React, { useState } from 'react';
import axios from 'axios';

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
    const [error, setError] = useState<string | null>(null);
    const [hasSearched, setHasSearched] = useState(false);

    const resetState = () => {
        setSearchTerm('');
        setSearchType('legacy_id');
        setMatch(null);
        setError(null);
        setSearching(false);
        setHasSearched(false);
    };

    const handleClose = () => {
        resetState();
        onClose();
    };

    const lookup = async () => {
        setError(null);
        setMatch(null);
        setSearching(true);

        try {
            const response = await axios.post('/search', {
                searchTerm,
                searchType,
            });

            setMatch(response.data.match ?? null);
        } catch (err: any) {
            if (err.response?.status === 422) {
                const message =
                    err.response.data.errors?.searchTerm?.join(' ') ||
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

    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div className="w-full max-w-lg rounded bg-white shadow-lg">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h2 className="text-lg font-semibold">Lookup Tool</h2>
                    <button className="text-gray-500 hover:text-gray-700" onClick={handleClose}>
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

                    {error && <div className="mb-4 rounded border border-red-400 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                    <div>
                        {match && (
                            <div className="rounded border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-800">
                                <p>
                                    <strong>Legacy ID:</strong> {match['Provider Legacy ID']}
                                </p>
                                <p>
                                    <strong>NPI:</strong> {match['Provider NPI']}
                                </p>
                                <p>
                                    <strong>Line Description:</strong> {match['Line Description']}
                                </p>
                            </div>
                        )}

                        {!match && !error && hasSearched && !searching && (
                            <p className="text-sm text-gray-600">No results found.</p>
                        )}
                    </div>
                </div>

                <div className="flex justify-end border-t px-4 py-3">
                    <button className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100" onClick={handleClose}>
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}