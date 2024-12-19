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
    NID: '',
    LIC: '',
    name: '',
  });
  const [message, setMessage] = useState(initialMessage);
  const [errors, setErrors] = useState<Errors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prevData => ({ ...prevData, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setIsSubmitting(true);

    try {
      const responseMessage = await submitRecord(formData);
      setMessage(responseMessage);
      setFormData({ NID: '', LIC: '', name: '' });
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
      <Head title="NID Lookup" />
      <div className="container">
        <button 
          className="search-button"
          onClick={() => setIsSearchModalOpen(true)}
        >
          Search
        </button>
        <h1 className="title">NID Lookup</h1>
        {message && <div className="message">{message}</div>}
        <form onSubmit={handleSubmit} className="form">
          <div className="input-group">
            <label htmlFor="NID" className="label">NID</label>
            <input
              type="text"
              id="NID"
              name="NID"
              value={formData.NID}
              onChange={handleInputChange}
              className="input"
            />
            {errors.NID && <div className="error">{errors.NID}</div>}
                            </div>
          <div className="input-group">
            <label htmlFor="LIC" className="label">LIC</label>
            <input
              type="text"
              id="LIC"
              name="LIC"
              value={formData.LIC}
              onChange={handleInputChange}
              className="input"
            />
            {errors.LIC && <div className="error">{errors.LIC}</div>}
                                    </div>
          <div className="input-group">
            <label htmlFor="name" className="label">Name</label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleInputChange}
              className="input"
            />
            {errors.name && <div className="error">{errors.name}</div>}
                                            </div>
          <button type="submit" className="submit-button" disabled={isSubmitting}>
            Submit
          </button>
        </form>
                                            </div>
      <SearchModal 
        isOpen={isSearchModalOpen} 
        onClose={() => setIsSearchModalOpen(false)} 
      />
        </>
    );
}
