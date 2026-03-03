import axios from 'axios';
import { SearchResult, SubmitRecordPayload } from '../types';

type SearchType = 'legacy_id' | 'npi';

interface SearchResponse {
    success: boolean;
    match: SearchResult | null;
}

interface SubmitRecordResponse {
    success: boolean;
    message: string;
}

export const searchRecords = async (
    query: string,
    searchType: SearchType = 'legacy_id',
): Promise<SearchResult | null> => {
    try {
        const response = await axios.post<SearchResponse>('/search', {
            query,
            searchType,
        });
        return response.data.match;
    } catch (error) {
        console.error('Error searching records:', error);
        throw error;
    }
};

export const submitRecord = async (
    data: SubmitRecordPayload,
): Promise<string> => {
    try {
        const response = await axios.post<SubmitRecordResponse>(
            '/submit-record',
            data,
        );
        return response.data.message;
    } catch (error) {
        console.error('Error submitting record:', error);
        throw error;
    }
};
