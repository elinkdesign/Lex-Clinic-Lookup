import React from 'react';
import { Head } from '@inertiajs/react';
import { lightTheme } from '../Styles/Theme';

const styles: { [key: string]: React.CSSProperties } = {
  header: {
    backgroundColor: lightTheme.primary,
    padding: '1rem 0',
  },
  headerContent: {
    maxWidth: '1200px',
    margin: '0 auto',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  logo: {
    color: lightTheme.body,
    margin: 0,
  },
  navUl: {
    listStyleType: 'none',
    padding: 0,
    display: 'flex',
    gap: '1rem',
  },
  navA: {
    color: lightTheme.body,
    textDecoration: 'none',
    fontWeight: 'bold',
  },
  main: {
    maxWidth: '1200px',
    margin: '2rem auto',
    padding: '0 1rem',
  },
  footer: {
    backgroundColor: lightTheme.accent,
    color: lightTheme.text,
    textAlign: 'center',
    padding: '1rem 0',
    position: 'absolute',
    bottom: 0,
    width: '100%',
  },
};

interface WelcomeProps {
  auth: {
    user: any;
  };
}

export default function Welcome({ auth }: WelcomeProps) {
  return (
    <>
      <Head title="Welcome" />
      <header style={styles.header}>
        <div style={styles.headerContent}>
          <h1 style={styles.logo}>LCI Lookup</h1>
          <nav>
            <ul style={styles.navUl}>
              <li><a href="#" style={styles.navA}>Home</a></li>
              <li><a href="#" style={styles.navA}>Services</a></li>
              <li><a href="#" style={styles.navA}>About</a></li>
              <li><a href="#" style={styles.navA}>Contact</a></li>
              {auth.user ? (
                <li><a href={route('dashboard')} style={styles.navA}>Dashboard</a></li>
              ) : (
                <>
                  <li><a href={route('login')} style={styles.navA}>Log in</a></li>
                  <li><a href={route('register')} style={styles.navA}>Register</a></li>
                </>
              )}
            </ul>
          </nav>
        </div>
      </header>
      <main style={styles.main}>
        <h2>Welcome to LCI Lookup</h2>
        <p>This is a placeholder for your main content. You can add more sections, images, and other components here.</p>
      </main>
      <footer style={styles.footer}>
        <p>&copy; 2023 LCI Lookup. All rights reserved.</p>
      </footer>
    </>
  );
}
