import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import '../Styles/SearchModal.css';
import { searchRecords } from '../Services/api';
import { SearchResult } from '../types';

interface SearchModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const SearchModal: React.FC<SearchModalProps> = ({ isOpen, onClose }) => {
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [hasSearched, setHasSearched] = useState(false);
  const { data, setData, reset, processing, clearErrors } = useForm({
    searchTerm: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    clearErrors();
    setErrorMessage(null);
    setHasSearched(true);
    try {
      const results = await searchRecords(data.searchTerm);
      setSearchResults(results || []);
    } catch (error: any) {
      console.error('Search error:', error);
      setErrorMessage(error.response?.data?.message || 'An error occurred while searching. Please try again.');
      setSearchResults([]);
    }
  };

  const handleClose = () => {
    onClose();
    reset();
    setSearchResults([]);
    setErrorMessage(null);
    setHasSearched(false);
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay">
      <div className="modal-content">
        <div className="modal-header">
          <h2>Search Records</h2>
          <button className="close-button" onClick={handleClose}>
            <span className="close-icon">&times;</span>
            <span className="close-text">Close</span>
          </button>
        </div>
        <form onSubmit={handleSubmit} className="search-form">
          <div className="search-input-container">
            <input
              type="text"
              placeholder="Enter NID or LIC"
              value={data.searchTerm}
              onChange={e => setData('searchTerm', e.target.value)}
              className="search-input"
            />
          </div>
          {errorMessage && <div className="error-message">{errorMessage}</div>}
          {searchResults.length > 0 ? (
            <div className="results">
              <h3>Results:</h3>
              <ul className="results-list">
                {searchResults.map((result, index) => (
                  <li key={index} className="result-item">
                    NID: {result.NID}, LIC: {result.LIC}, Name: {result.name}
                  </li>
                ))}
              </ul>
            </div>
          ) : hasSearched ? (
            <div className="no-results">No results found</div>
          ) : null}
          <button type="submit" className="modal-search-button" disabled={processing}>
            Search
          </button>
        </form>
      </div>
    </div>
  );
};

export default SearchModal; 