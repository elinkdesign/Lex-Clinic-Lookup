import { createGlobalStyle } from 'styled-components';

export const lightTheme = {
  body: '#ffffff',
  text: '#333333',
  primary: '#0077be',
  secondary: '#4a4a4a',
  accent: '#f0f0f0',
  font: "'Open Sans', sans-serif",
};

export const darkTheme = {
  body: '#333333',
  text: '#f0f0f0',
  primary: '#0099cc',
  secondary: '#cccccc',
  accent: '#4a4a4a',
  font: "'Open Sans', sans-serif",
};

export const GlobalStyles = createGlobalStyle`
  body {
    background: ${({ theme }) => theme.body};
    color: ${({ theme }) => theme.text};
    font-family: ${({ theme }) => theme.font};
    transition: all 0.3s linear;
  }

  a {
    color: ${({ theme }) => theme.primary};
    text-decoration: none;
  }

  button {
    background: ${({ theme }) => theme.primary};
    color: ${({ theme }) => theme.body};
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;

    &:hover {
      background: ${({ theme }) => theme.secondary};
    }
  }

  h1, h2, h3, h4, h5, h6 {
    color: ${({ theme }) => theme.primary};
  }
`; 