export interface SearchResult {
  NID: string;
  LIC: string;
  name: string;
}

export interface FormData {
  NID: string;
  LIC: string;
  name: string;
}

export interface Errors {
  [key: string]: string;
} 