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

export const applyGlobalStyles = () => {
  document.body.style.background = lightTheme.body;
  document.body.style.color = lightTheme.text;
  document.body.style.fontFamily = lightTheme.font;
  document.body.style.transition = 'all 0.3s linear';
};