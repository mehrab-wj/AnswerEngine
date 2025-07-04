import { Icon } from '@/components/icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { Calendar, Clock, Eye, FileText, Globe, Link, Search, User, ExternalLink } from 'lucide-react';
import { useState } from 'react';

interface LinkObject {
    href: string;
    text: string;
    title: string;
    base_domain: string;
}

interface ScrapeResult {
    id: number;
    title: string;
    source_url: string;
    author: string | null;
    content: string;
    content_length: number;
    internal_links: (string | LinkObject)[];
    external_links: (string | LinkObject)[];
    created_at: string;
}

interface ProcessData {
    id: number;
    uuid: string;
    url: string;
    status: string;
    error_message: string | null;
    created_at: string;
    updated_at: string;
    processing_time: number | null;
}

interface Stats {
    total_links: number;
    completed_links: number;
    failed_links: number;
    total_content_length: number;
    average_content_length: number;
}

interface WebsiteDetailsProps {
    process: ProcessData;
    scrapeResults: ScrapeResult[];
    stats: Stats;
}

export default function WebsiteDetails({ process, scrapeResults, stats }: WebsiteDetailsProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedResult, setSelectedResult] = useState<ScrapeResult | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    usePoll(2000);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Website Process', href: '#' },
    ];

    const filteredResults = scrapeResults.filter(
        (result) =>
            result.title.toLowerCase().includes(searchTerm.toLowerCase()) || result.source_url.toLowerCase().includes(searchTerm.toLowerCase()),
    );

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return (
                    <Badge variant="secondary" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                        Completed
                    </Badge>
                );
            case 'processing':
                return (
                    <Badge variant="secondary" className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                        Processing
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge variant="secondary" className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                        Pending
                    </Badge>
                );
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const formatProcessingTime = (seconds: number | null) => {
        if (!seconds) return 'N/A';
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}m ${remainingSeconds}s`;
    };

    const handleViewContent = (result: ScrapeResult) => {
        setSelectedResult(result);
        setIsDialogOpen(true);
    };

    const renderLinkItem = (link: string | LinkObject, index: number, type: 'internal' | 'external') => {
        if (typeof link === 'string') {
            return (
                <div key={index} className="flex items-center gap-2 p-2 rounded-md border bg-card hover:bg-accent transition-colors">
                    <Icon iconNode={type === 'internal' ? Link : ExternalLink} className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <a
                        href={link}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-blue-600 hover:text-blue-800 truncate"
                        title={link}
                    >
                        {link}
                    </a>
                </div>
            );
        }

        // Handle object format
        const linkText = link.text || link.href || link.base_domain || 'Unknown Link';
        const linkTitle = link.title || link.href;
        
        return (
            <div key={index} className="flex items-center gap-2 p-3 rounded-md border bg-card hover:bg-accent transition-colors">
                <Icon iconNode={type === 'internal' ? Link : ExternalLink} className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <div className="flex-1 min-w-0">
                    <a
                        href={link.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm font-medium text-blue-600 hover:text-blue-800 truncate block"
                        title={linkTitle}
                    >
                        {linkText}
                    </a>
                    {link.base_domain && (
                        <div className="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                            <Globe className="h-3 w-3" />
                            {link.base_domain}
                        </div>
                    )}
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Website Process - ${process.url}`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                {/* Header */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <div className="flex-1">
                                <CardTitle className="flex items-center gap-2">
                                    <Icon iconNode={Globe} className="h-5 w-5" />
                                    Website Scraping Process
                                </CardTitle>
                            </div>
                            {getStatusBadge(process.status)}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Globe} className="h-4 w-4" />
                                    URL
                                </div>
                                <div className="font-medium break-all">{process.url}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Calendar} className="h-4 w-4" />
                                    Started
                                </div>
                                <div className="font-medium">{process.created_at}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Clock} className="h-4 w-4" />
                                    Processing Time
                                </div>
                                <div className="font-medium">{formatProcessingTime(process.processing_time)}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={FileText} className="h-4 w-4" />
                                    UUID
                                </div>
                                <div className="font-mono text-sm">{process.uuid}</div>
                            </div>
                        </div>

                        {process.error_message && (
                            <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                <div className="text-sm font-medium text-red-800 dark:text-red-300">Error:</div>
                                <div className="mt-1 text-sm text-red-700 dark:text-red-400">{process.error_message}</div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Links</p>
                                    <p className="text-2xl font-bold">{stats.total_links}</p>
                                </div>
                                <Icon iconNode={Link} className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Completed</p>
                                    <p className="text-2xl font-bold text-green-600">{stats.completed_links}</p>
                                </div>
                                <Icon iconNode={FileText} className="h-8 w-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Scraped Links Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Icon iconNode={FileText} className="h-5 w-5" />
                                Scraped Links ({filteredResults.length})
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Icon iconNode={Search} className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search links..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-64 pl-8"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Title</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">URL</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Author</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Created</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredResults.map((result) => (
                                        <tr key={result.id} className="border-b hover:bg-muted/50">
                                            <td className="px-4 py-3">
                                                <div className="max-w-xs truncate font-medium" title={result.title}>
                                                    {result.title}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <a
                                                    href={result.source_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-block max-w-xs truncate text-blue-600 hover:text-blue-800"
                                                    title={result.source_url}
                                                >
                                                    {result.source_url}
                                                </a>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    {result.author && (
                                                        <>
                                                            <Icon iconNode={User} className="h-4 w-4 text-muted-foreground" />
                                                            <span className="text-sm">{result.author}</span>
                                                        </>
                                                    )}
                                                    {!result.author && <span className="text-sm text-muted-foreground">Unknown</span>}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-sm">{new Date(result.created_at).toLocaleDateString()}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Button variant="outline" size="sm" onClick={() => handleViewContent(result)}>
                                                    <Icon iconNode={Eye} className="mr-2 h-4 w-4" />
                                                    View Content
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {filteredResults.length === 0 && (
                                <div className="py-8 text-center text-muted-foreground">
                                    {searchTerm ? 'No links found matching your search.' : 'No links have been scraped yet.'}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Content Dialog */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-h-[80vh] sm:max-w-2xl lg:max-w-6xl overflow-y-auto">
                    {selectedResult && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="text-lg font-semibold">{selectedResult.title}</DialogTitle>
                                <div className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Globe} className="h-4 w-4" />
                                    <a
                                        href={selectedResult.source_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:text-blue-800"
                                    >
                                        {selectedResult.source_url}
                                    </a>
                                </div>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                    
                                    {selectedResult.author && (
                                        <div className="flex items-center gap-2">
                                            <Icon iconNode={User} className="h-4 w-4" />
                                            {selectedResult.author}
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <Icon iconNode={Calendar} className="h-4 w-4" />
                                        {selectedResult.created_at}
                                    </div>
                                </div>
                                <div className="border-t pt-4">
                                    <h4 className="mb-2 font-medium">Content:</h4>
                                    <div className="max-h-96 overflow-y-auto rounded-md bg-muted p-4">
                                        <pre className="text-sm leading-relaxed whitespace-pre-wrap">
                                            {selectedResult.content}
                                        </pre>
                                    </div>
                                </div>
                                {(selectedResult.internal_links.length > 0 || selectedResult.external_links.length > 0) && (
                                    <div className="border-t pt-4">
                                        <h4 className="mb-4 font-medium">Links Found:</h4>
                                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                            {selectedResult.internal_links.length > 0 && (
                                                <div>
                                                    <h5 className="mb-3 text-sm font-medium flex items-center gap-2">
                                                        <Icon iconNode={Link} className="h-4 w-4" />
                                                        Internal Links ({selectedResult.internal_links.length})
                                                    </h5>
                                                    <div className="max-h-64 space-y-2 overflow-y-auto">
                                                        {selectedResult.internal_links.map((link, index) => renderLinkItem(link, index, 'internal'))}
                                                    </div>
                                                </div>
                                            )}
                                            {selectedResult.external_links.length > 0 && (
                                                <div>
                                                    <h5 className="mb-3 text-sm font-medium flex items-center gap-2">
                                                        <Icon iconNode={ExternalLink} className="h-4 w-4" />
                                                        External Links ({selectedResult.external_links.length})
                                                    </h5>
                                                    <div className="max-h-64 space-y-2 overflow-y-auto">
                                                        {selectedResult.external_links.map((link, index) => renderLinkItem(link, index, 'external'))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
