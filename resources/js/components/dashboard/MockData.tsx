export interface StatData {
  totalDocs: number;
  processing: number;
  vectorSynced: number;
  totalQueries: number;
}

export interface ProcessingItem {
  id: number;
  type: 'pdf' | 'website';
  name: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  vectorSync: 'pending' | "processing" | 'completed' | 'failed';
  createdAt: string;
  size?: string;
  pages?: number;
  processingTime?: string;
}

export interface JsonResult {
  id: string;
  title: string;
  content: string;
  source: string;
  similarity: number;
}

export interface SearchResult {
  query: string;
  aiAnswer: string;
  jsonResults: JsonResult[];
  processingTime: number;
}

export const mockStats: StatData = {
  totalDocs: 24,
  processing: 3,
  vectorSynced: 21,
  totalQueries: 156
};

export const mockProcessingData: ProcessingItem[] = [
  {
    id: 1,
    type: 'pdf',
    name: 'interview-guide.pdf',
    status: 'completed',
    vectorSync: 'completed',
    createdAt: '2024-01-15',
    size: '2.4 MB',
    pages: 45,
    processingTime: '2.3s'
  },
  {
    id: 2,
    type: 'website',
    name: 'https://docs.example.com',
    status: 'processing',
    vectorSync: 'pending',
    createdAt: '2024-01-15',
    pages: 12,
    processingTime: '45s'
  },
  {
    id: 3,
    type: 'pdf',
    name: 'technical-specifications.pdf',
    status: 'completed',
    vectorSync: 'completed',
    createdAt: '2024-01-14',
    size: '8.7 MB',
    pages: 120,
    processingTime: '8.1s'
  },
  {
    id: 4,
    type: 'website',
    name: 'https://blog.example.com',
    status: 'failed',
    vectorSync: 'failed',
    createdAt: '2024-01-14',
    pages: 0,
    processingTime: 'N/A'
  },
  {
    id: 5,
    type: 'pdf',
    name: 'user-manual.pdf',
    status: 'pending',
    vectorSync: 'pending',
    createdAt: '2024-01-15',
    size: '1.2 MB',
    pages: 24,
    processingTime: 'N/A'
  },
  {
    id: 6,
    type: 'website',
    name: 'https://support.example.com',
    status: 'completed',
    vectorSync: 'completed',
    createdAt: '2024-01-13',
    pages: 8,
    processingTime: '23s'
  }
];

export const mockSearchResult: SearchResult = {
  query: '',
  aiAnswer: '',
  jsonResults: [],
  processingTime: 0
};

export const sampleSearchResults: SearchResult[] = [
  {
    query: 'What is the installation process?',
    aiAnswer: 'Based on the documentation, the installation process involves three main steps: 1) Download the installer from the official website, 2) Run the installer with administrator privileges, and 3) Follow the setup wizard to configure your preferences. The process typically takes 5-10 minutes depending on your system specifications.',
    jsonResults: [
      {
        id: 'doc_1',
        title: 'Installation Guide',
        content: 'To install the software, download the installer from our website...',
        source: 'installation-guide.pdf',
        similarity: 0.95
      },
      {
        id: 'doc_2',
        title: 'System Requirements',
        content: 'Before installation, ensure your system meets the minimum requirements...',
        source: 'technical-specifications.pdf',
        similarity: 0.87
      }
    ],
    processingTime: 1.2
  },
  {
    query: 'How to troubleshoot common errors?',
    aiAnswer: 'Common errors can be resolved by following these steps: 1) Check the error logs in the application directory, 2) Verify your system meets all requirements, 3) Restart the application as administrator, and 4) If issues persist, contact support with the error code.',
    jsonResults: [
      {
        id: 'doc_3',
        title: 'Troubleshooting Guide',
        content: 'This section covers common issues and their solutions...',
        source: 'user-manual.pdf',
        similarity: 0.92
      },
      {
        id: 'doc_4',
        title: 'Error Codes Reference',
        content: 'Error code explanations and resolution steps...',
        source: 'https://support.example.com/errors',
        similarity: 0.88
      }
    ],
    processingTime: 1.8
  }
]; 