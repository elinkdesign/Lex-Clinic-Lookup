import { Head } from '@inertiajs/react';
import axios from 'axios';
import React, { useState } from 'react';
import SearchModal from '../Components/SearchModal';
import { submitRecord } from '../Services/api';
import '../Styles/Welcome.css';
import {
    ConflictResponse,
    DuplicateMatches,
    Errors,
    FormData,
    SearchResult,
} from '../types';

interface WelcomeProps {
    message?: string;
}

export default function Welcome({ message: initialMessage }: WelcomeProps) {
    const [isSearchModalOpen, setIsSearchModalOpen] = useState(false);
    const [formData, setFormData] = useState<FormData>({
        legacy_id: '',
        npi: '',
        line_description: '',
    });
    const [message, setMessage] = useState(initialMessage);
    const [errors, setErrors] = useState<Errors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isOverwriting, setIsOverwriting] = useState(false);
    const [conflictMessage, setConflictMessage] = useState('');
    const [duplicateMatches, setDuplicateMatches] =
        useState<DuplicateMatches | null>(null);

    const clearConflictState = () => {
        setConflictMessage('');
        setDuplicateMatches(null);
        setIsOverwriting(false);
    };

    const normalizeErrors = (
        incomingErrors: Record<string, string[] | string> | undefined,
    ): Errors => {
        if (!incomingErrors) {
            return {};
        }

        return Object.entries(incomingErrors).reduce<Errors>(
            (acc, [key, value]) => {
                acc[key] = Array.isArray(value) ? value.join(' ') : value;
                return acc;
            },
            {},
        );
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;

        // Apply input masks/validation
        let processedValue = value;

        if (name === 'legacy_id') {
            // Max 10 characters, alphanumeric
            processedValue = value.slice(0, 10).toUpperCase();
        } else if (name === 'npi') {
            // Only digits, max 10
            processedValue = value.replace(/\D/g, '').slice(0, 10);
        } else if (name === 'line_description') {
            // Alphanumeric, spaces, and hyphens only, max 50
            processedValue = value.replace(/[^A-Za-z0-9 -]/g, '').slice(0, 50);
        }

        setFormData((prevData) => ({ ...prevData, [name]: processedValue }));
    };

    const submitForm = async (deleteExistingRecords = false) => {
        setErrors({});
        if (!deleteExistingRecords) {
            clearConflictState();
        }
        setIsSubmitting(true);
        setIsOverwriting(deleteExistingRecords);

        try {
            const responseMessage = await submitRecord({
                ...formData,
                delete_existing_records: deleteExistingRecords,
            });

            setMessage(responseMessage);
            setFormData({ legacy_id: '', npi: '', line_description: '' });
            clearConflictState();
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 409) {
                const responseData = error.response.data as
                    | ConflictResponse
                    | undefined;
                setConflictMessage(
                    responseData?.message ?? 'Matching records already exist.',
                );
                setDuplicateMatches(
                    responseData?.duplicates ?? { longList: [], shortList: [] },
                );
                setMessage(undefined);
            } else if (
                axios.isAxiosError(error) &&
                error.response?.status === 422
            ) {
                const validationErrors = normalizeErrors(
                    error.response.data?.errors,
                );
                setErrors(validationErrors);
                clearConflictState();
            } else {
                setMessage('An error occurred. Please try again.');
            }
        } finally {
            setIsSubmitting(false);
            setIsOverwriting(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        await submitForm(false);
    };

    const handleOverwriteSubmit = async () => {
        await submitForm(true);
    };

    const renderDuplicateRows = (matches: SearchResult[]) => {
        if (matches.length === 0) {
            return (
                <tr>
                    <td colSpan={3} className="duplicates-empty">
                        No matches.
                    </td>
                </tr>
            );
        }

        return matches.map((match, index) => (
            <tr
                key={`${match['Provider Legacy ID']}-${match['Provider NPI']}-${index}`}
            >
                <td>{match['Provider Legacy ID']}</td>
                <td>{match['Provider NPI']}</td>
                <td>{match['Line Description']}</td>
            </tr>
        ));
    };

    return (
        <>
            <Head title="CPDR List Form - NID Lookup" />
            <div className="container">
                <div className="header-actions">
                    <button
                        className="search-button"
                        onClick={() => setIsSearchModalOpen(true)}
                    >
                        Lookup
                    </button>
                </div>

                <h1 className="title">Provider Legacy ID and NPI List</h1>

                {message && <div className="message success">{message}</div>}
                {conflictMessage && (
                    <div className="message error">{conflictMessage}</div>
                )}

                {duplicateMatches && (
                    <div className="duplicates-panel">
                        <h2 className="duplicates-title">
                            Existing matching records found
                        </h2>
                        <p className="duplicates-description">
                            Review these records before continuing. You can
                            clear the existing records and submit again.
                        </p>

                        <div className="duplicates-section">
                            <h3>Long List Matches</h3>
                            <table className="duplicates-table">
                                <thead>
                                    <tr>
                                        <th>Legacy ID</th>
                                        <th>NPI</th>
                                        <th>Line Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {renderDuplicateRows(
                                        duplicateMatches.longList,
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="duplicates-section">
                            <h3>Short List Matches</h3>
                            <table className="duplicates-table">
                                <thead>
                                    <tr>
                                        <th>Legacy ID</th>
                                        <th>NPI</th>
                                        <th>Line Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {renderDuplicateRows(
                                        duplicateMatches.shortList,
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="duplicates-actions">
                            <button
                                type="button"
                                className="duplicates-button primary"
                                onClick={handleOverwriteSubmit}
                                disabled={isSubmitting}
                            >
                                {isOverwriting
                                    ? 'Deleting & Re-Submitting...'
                                    : 'Delete Existing Records & Re-Submit'}
                            </button>
                            <button
                                type="button"
                                className="duplicates-button secondary"
                                onClick={clearConflictState}
                                disabled={isSubmitting}
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="form">
                    <div className="form-group">
                        <label htmlFor="legacy-id" className="label">
                            Legacy ID
                        </label>
                        <input
                            type="text"
                            id="legacy-id"
                            name="legacy_id"
                            value={formData.legacy_id}
                            onChange={handleInputChange}
                            className="form-control"
                            maxLength={10}
                            placeholder="Enter Legacy ID"
                        />
                        {errors.legacy_id && (
                            <div className="error">{errors.legacy_id}</div>
                        )}
                    </div>

                    <div className="form-group">
                        <label htmlFor="npi" className="label">
                            NPI
                        </label>
                        <input
                            type="text"
                            id="npi"
                            name="npi"
                            value={formData.npi}
                            onChange={handleInputChange}
                            className="form-control"
                            maxLength={10}
                            placeholder="Enter 10-digit NPI"
                        />
                        {errors.npi && (
                            <div className="error">{errors.npi}</div>
                        )}
                    </div>

                    <div className="form-group">
                        <label htmlFor="line-description" className="label">
                            Line Description
                        </label>
                        <input
                            type="text"
                            id="line-description"
                            name="line_description"
                            value={formData.line_description}
                            onChange={handleInputChange}
                            className="form-control"
                            maxLength={50}
                            placeholder="Enter Line Description"
                        />
                        {errors.line_description && (
                            <div className="error">
                                {errors.line_description}
                            </div>
                        )}
                    </div>

                    <div className="submit-container">
                        <button
                            type="submit"
                            className="submit-button"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit'}
                        </button>
                    </div>
                </form>
            </div>

            <SearchModal
                isOpen={isSearchModalOpen}
                onClose={() => setIsSearchModalOpen(false)}
            />
        </>
    );
}
