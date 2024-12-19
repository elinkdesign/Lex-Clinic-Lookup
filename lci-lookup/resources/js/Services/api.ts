import axios from 'axios';
import { SearchResult, FormData } from '../types';

export const searchRecords = async (searchTerm: string): Promise<SearchResult[]> => {
  try {
    const response = await axios.post('/search', { searchTerm });
    return response.data.results;
  } catch (error) {
    console.error('Error searching records:', error);
    throw error;
  }
};

export const submitRecord = async (data: FormData): Promise<string> => {
  try {
    const response = await axios.post('/submit-record', data);
    return response.data.message;
  } catch (error) {
    console.error('Error submitting record:', error);
    throw error;
  }
}; 