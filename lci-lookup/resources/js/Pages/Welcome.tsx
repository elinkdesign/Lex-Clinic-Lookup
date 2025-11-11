import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import SearchModal from '../Components/SearchModal';
import '../Styles/Welcome.css';
import { submitRecord } from '../Services/api';
import { FormData, Errors } from '../types';

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
    
    setFormData(prevData => ({ ...prevData, [name]: processedValue }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setIsSubmitting(true);

    try {
      const responseMessage = await submitRecord(formData);
      setMessage(responseMessage);
      setFormData({ legacy_id: '', npi: '', line_description: '' });
    } catch (error: any) {
      if (error.response && error.response.data && error.response.data.errors) {
        setErrors(error.response.data.errors);
      } else {
        setMessage('An error occurred. Please try again.');
      }
    } finally {
      setIsSubmitting(false);
    }
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
        
        <form onSubmit={handleSubmit} className="form">
          <div className="form-group">
            <label htmlFor="legacy-id" className="label">Legacy ID</label>
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
            {errors.legacy_id && <div className="error">{errors.legacy_id}</div>}
          </div>
          
          <div className="form-group">
            <label htmlFor="npi" className="label">NPI</label>
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
            {errors.npi && <div className="error">{errors.npi}</div>}
          </div>
          
          <div className="form-group">
            <label htmlFor="line-description" className="label">Line Description</label>
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
            {errors.line_description && <div className="error">{errors.line_description}</div>}
          </div>
          
          <div className="submit-container">
            <button type="submit" className="submit-button" disabled={isSubmitting}>
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
