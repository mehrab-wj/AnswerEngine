import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Icon } from '@/components/icon';
import { Link, Upload, Plus, X } from 'lucide-react';

export function AddSourcesSection() {
  const [websiteUrl, setWebsiteUrl] = useState('');
  const [isUrlValid, setIsUrlValid] = useState(true);
  const [dragActive, setDragActive] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const validateUrl = (url: string): boolean => {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  };

  const handleUrlChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const url = e.target.value;
    setWebsiteUrl(url);
    if (url.trim()) {
      setIsUrlValid(validateUrl(url));
    } else {
      setIsUrlValid(true);
    }
  };

  const handleAddWebsite = () => {
    if (websiteUrl.trim() && isUrlValid) {
      console.log('Adding website:', websiteUrl);
      // Here you would dispatch the action to add the website
      setWebsiteUrl('');
    }
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        setSelectedFile(file);
      } else {
        alert('Please select a PDF file');
      }
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        setSelectedFile(file);
      } else {
        alert('Please select a PDF file');
      }
    }
  };

  const handleUploadPdf = () => {
    if (selectedFile) {
      console.log('Uploading PDF:', selectedFile.name);
      // Here you would dispatch the action to upload the PDF
      setSelectedFile(null);
    }
  };

  const clearSelectedFile = () => {
    setSelectedFile(null);
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
      {/* Website URL Section */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Icon iconNode={Link} className="h-5 w-5" />
            Add Website
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Input
              type="url"
              placeholder="https://example.com"
              value={websiteUrl}
              onChange={handleUrlChange}
              className={!isUrlValid ? 'border-red-500' : ''}
            />
            {!isUrlValid && (
              <p className="text-sm text-red-500">Please enter a valid URL</p>
            )}
          </div>
          <Button 
            onClick={handleAddWebsite}
            disabled={!websiteUrl.trim() || !isUrlValid}
            className="w-full"
          >
            <Icon iconNode={Plus} className="h-4 w-4 mr-2" />
            Add Website
          </Button>
        </CardContent>
      </Card>

      {/* PDF Upload Section */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Icon iconNode={Upload} className="h-5 w-5" />
            Upload PDF
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div
            className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors ${
              dragActive 
                ? 'border-primary bg-primary/10' 
                : 'border-gray-300 dark:border-gray-700'
            }`}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
          >
            {selectedFile ? (
              <div className="space-y-2">
                <div className="flex items-center justify-center gap-2">
                  <Icon iconNode={Upload} className="h-6 w-6 text-green-500" />
                  <span className="text-sm font-medium">{selectedFile.name}</span>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={clearSelectedFile}
                    className="p-1 h-6 w-6"
                  >
                    <Icon iconNode={X} className="h-4 w-4" />
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                  {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                </p>
              </div>
            ) : (
              <div className="space-y-2">
                <Icon iconNode={Upload} className="h-8 w-8 mx-auto text-gray-400" />
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  Drag and drop a PDF file here, or click to browse
                </p>
                <input
                  type="file"
                  accept=".pdf"
                  onChange={handleFileSelect}
                  className="hidden"
                  id="pdf-upload"
                />
                <label htmlFor="pdf-upload" className="cursor-pointer">
                  <Button variant="outline" size="sm" asChild>
                    <span>Browse Files</span>
                  </Button>
                </label>
              </div>
            )}
          </div>
          <Button 
            onClick={handleUploadPdf}
            disabled={!selectedFile}
            className="w-full"
          >
            <Icon iconNode={Upload} className="h-4 w-4 mr-2" />
            Upload PDF
          </Button>
        </CardContent>
      </Card>
    </div>
  );
} 