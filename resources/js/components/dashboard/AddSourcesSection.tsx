import { Icon } from '@/components/icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Link, Plus, Upload, X } from 'lucide-react';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export function AddSourcesSection() {
    const { errors } = usePage().props;
    const [websiteUrl, setWebsiteUrl] = useState('');
    const [isUrlValid, setIsUrlValid] = useState(true);
    const [crawlDepth, setCrawlDepth] = useState('2'); // Default to "surface"
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isUploading, setIsUploading] = useState(false);

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
            setIsSubmitting(true);
            
            router.post(route('dashboard.add-website'), {
                url: websiteUrl,
                depth: parseInt(crawlDepth)
            }, {
                onSuccess: () => {
                    setWebsiteUrl('');
                    setCrawlDepth('2');
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                }
            });
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
            setIsUploading(true);
            
            const formData = new FormData();
            formData.append('file', selectedFile);
            
            router.post(route('dashboard.upload-pdf'), formData, {
                onSuccess: () => {
                    setSelectedFile(null);
                    setIsUploading(false);
                },
                onError: () => {
                    setIsUploading(false);
                }
            });
        }
    };

    const clearSelectedFile = () => {
        setSelectedFile(null);
    };

    return (
        <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            {/* Website URL Section */}
            <div>
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
                                className={!isUrlValid || errors.url ? 'border-red-500' : ''}
                            />
                            {!isUrlValid && <p className="text-sm text-red-500">Please enter a valid URL</p>}
                            {errors.url && <p className="text-sm text-red-500">{errors.url}</p>}
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Crawl Depth</label>
                            <Select value={crawlDepth} onValueChange={setCrawlDepth}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select crawl depth" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">No depth</SelectItem>
                                    <SelectItem value="2">Surface</SelectItem>
                                    <SelectItem value="3">Deep</SelectItem>
                                    <SelectItem value="4">Super deep</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.depth && <p className="text-sm text-red-500">{errors.depth}</p>}
                        </div>
                        <Button onClick={handleAddWebsite} disabled={!websiteUrl.trim() || !isUrlValid || isSubmitting} className="w-full">
                            <Icon iconNode={Plus} className="mr-2 h-4 w-4" />
                            {isSubmitting ? 'Adding Website...' : 'Add Website'}
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {/* PDF Upload Section */}
            <div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Icon iconNode={Upload} className="h-5 w-5" />
                            Upload PDF
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div
                            className={`rounded-lg border-2 border-dashed p-6 text-center transition-colors ${
                                dragActive ? 'border-primary bg-primary/10' : 
                                errors.file ? 'border-red-500 bg-red-50 dark:bg-red-950/20' : 
                                'border-gray-300 dark:border-gray-700'
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
                                        <Button variant="ghost" size="sm" onClick={clearSelectedFile} className="h-6 w-6 p-1">
                                            <Icon iconNode={X} className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">{(selectedFile.size / 1024 / 1024).toFixed(2)} MB</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Icon iconNode={Upload} className="mx-auto h-8 w-8 text-gray-400" />
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Drag and drop a PDF file here, or click to browse</p>
                                    <input type="file" accept=".pdf" onChange={handleFileSelect} className="hidden" id="pdf-upload" />
                                    <label htmlFor="pdf-upload" className="cursor-pointer">
                                        <Button variant="outline" size="sm" asChild>
                                            <span>Browse Files</span>
                                        </Button>
                                    </label>
                                </div>
                            )}
                        </div>
                        {errors.file && <p className="text-sm text-red-500">{errors.file}</p>}
                        <Button onClick={handleUploadPdf} disabled={!selectedFile || isUploading} className="w-full">
                            <Icon iconNode={Upload} className="mr-2 h-4 w-4" />
                            {isUploading ? 'Uploading PDF...' : 'Upload PDF'}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
