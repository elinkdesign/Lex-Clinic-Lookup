import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { lightTheme } from '../Styles/Theme';

const styles: { [key: string]: React.CSSProperties } = {
  container: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '100vh',
    padding: '2rem',
    backgroundColor: lightTheme.body,
  },
  title: {
    fontSize: '2.5rem',
    color: lightTheme.primary,
    marginBottom: '2rem',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    width: '100%',
    maxWidth: '400px',
    gap: '1.5rem',
  },
  inputGroup: {
    display: 'flex',
    flexDirection: 'column',
  },
  label: {
    marginBottom: '0.5rem',
    color: lightTheme.text,
    fontSize: '1rem',
  },
  input: {
    padding: '0.75rem',
    fontSize: '1rem',
    border: `1px solid ${lightTheme.accent}`,
    borderRadius: '4px',
    backgroundColor: '#ffffff',
  },
  button: {
    padding: '0.75rem',
    fontSize: '1rem',
    backgroundColor: lightTheme.primary,
    color: '#ffffff',
    border: 'none',
    borderRadius: '4px',
    cursor: 'pointer',
    transition: 'background-color 0.3s ease',
  },
  error: {
    color: 'red',
    fontSize: '0.875rem',
    marginTop: '0.25rem',
  },
  message: {
    marginTop: '1rem',
    padding: '0.5rem',
    backgroundColor: '#4CAF50',
    color: 'white',
    borderRadius: '4px',
    textAlign: 'center',
  },
};

interface WelcomeProps {
  message?: string;
}

export default function Welcome({ message }: WelcomeProps) {
  const { data, setData, post, processing, errors } = useForm({
    NID: '',
    LIC: '',
    name: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/submit-record');
  };

  return (
    <>
      <Head title="NID Lookup" />
      <div style={styles.container}>
        <h1 style={styles.title}>NID Lookup</h1>
        {message && <div style={styles.message}>{message}</div>}
        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.inputGroup}>
            <label htmlFor="NID" style={styles.label}>NID</label>
            <input
              type="text"
              id="NID"
              value={data.NID}
              onChange={e => setData('NID', e.target.value)}
              style={styles.input}
            />
            {errors.NID && <div style={styles.error}>{errors.NID}</div>}
          </div>
          <div style={styles.inputGroup}>
            <label htmlFor="LIC" style={styles.label}>LIC</label>
            <input
              type="text"
              id="LIC"
              value={data.LIC}
              onChange={e => setData('LIC', e.target.value)}
              style={styles.input}
            />
            {errors.LIC && <div style={styles.error}>{errors.LIC}</div>}
          </div>
          <div style={styles.inputGroup}>
            <label htmlFor="name" style={styles.label}>Name</label>
            <input
              type="text"
              id="name"
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              style={styles.input}
            />
            {errors.name && <div style={styles.error}>{errors.name}</div>}
          </div>
          <button type="submit" style={styles.button} disabled={processing}>
            Submit
          </button>
        </form>
      </div>
    </>
  );
}
