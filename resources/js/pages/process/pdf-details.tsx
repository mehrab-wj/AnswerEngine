import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Icon } from '@/components/icon';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { Calendar, Clock, Eye, FileText, HardDrive, AlertCircle, CheckCircle, Loader2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ProcessData {
    id: number;
    uuid: string;
    original_filename: string;
    file_size: number;
    formatted_file_size: string;
    status: string;
    vector_sync_status: string;
    extracted_text: string | null;
    markdown_text: string | null;
    metadata: Record<string, unknown>;
    driver_used: string | null;
    processing_time: number | null;
    formatted_processing_time: string | null;
    error_message: string | null;
    created_at: string;
    updated_at: string;
}

interface Stats {
    page_count: number;
    text_length: number;
    markdown_length: number;
    document_title: string | null;
}

interface TimelineItem {
    step: string;
    status: string;
    timestamp: string | null;
    description: string;
}

interface PdfDetailsProps {
    process: ProcessData;
    stats: Stats;
    timeline: TimelineItem[];
}

export default function PdfDetails({ process, stats, timeline }: PdfDetailsProps) {
    const [contentView, setContentView] = useState<'text' | 'markdown'>('text');
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [dialogContent, setDialogContent] = useState<{type: 'text' | 'markdown', content: string, title: string} | null>(null);
    
    usePoll(2000);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'PDF Process', href: '#' },
    ];

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return <Badge variant="secondary" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Completed</Badge>;
            case 'processing':
                return <Badge variant="secondary" className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">Processing</Badge>;
            case 'pending':
                return <Badge variant="secondary" className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">Pending</Badge>;
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getTimelineIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <Icon iconNode={CheckCircle} className="h-5 w-5 text-green-600" />;
            case 'processing':
                return <Icon iconNode={Loader2} className="h-5 w-5 text-blue-600 animate-spin" />;
            case 'failed':
                return <Icon iconNode={XCircle} className="h-5 w-5 text-red-600" />;
            default:
                return <Icon iconNode={AlertCircle} className="h-5 w-5 text-gray-400" />;
        }
    };

    const formatBytes = (bytes: number) => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handleViewFullContent = (type: 'text' | 'markdown') => {
        if (type === 'text' && process.extracted_text) {
            setDialogContent({
                type: 'text',
                content: process.extracted_text,
                title: `Extracted Text - ${process.original_filename}`
            });
        } else if (type === 'markdown' && process.markdown_text) {
            setDialogContent({
                type: 'markdown',
                content: process.markdown_text,
                title: `Markdown Text - ${process.original_filename}`
            });
        }
        setIsDialogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`PDF Process - ${process.original_filename}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                {/* Header */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <div className="flex-1">
                                <CardTitle className="flex items-center gap-2">
                                    <Icon iconNode={FileText} className="h-5 w-5" />
                                    PDF Processing Details
                                </CardTitle>
                            </div>
                            {getStatusBadge(process.status)}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={FileText} className="h-4 w-4" />
                                    Filename
                                </div>
                                <div className="font-medium break-all">{process.original_filename}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={HardDrive} className="h-4 w-4" />
                                    File Size
                                </div>
                                <div className="font-medium">{process.formatted_file_size}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Calendar} className="h-4 w-4" />
                                    Uploaded
                                </div>
                                <div className="font-medium">{process.created_at}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Icon iconNode={Clock} className="h-4 w-4" />
                                    Processing Time
                                </div>
                                <div className="font-medium">{process.formatted_processing_time || 'N/A'}</div>
                            </div>
                        </div>
                        
                        {process.error_message && (
                            <Alert className="mt-4">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <span className="font-medium">Error:</span> {process.error_message}
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Pages</p>
                                    <p className="text-2xl font-bold">{stats.page_count || 'N/A'}</p>
                                </div>
                                <Icon iconNode={FileText} className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Text Length</p>
                                    <p className="text-2xl font-bold">{stats.text_length ? formatBytes(stats.text_length) : 'N/A'}</p>
                                </div>
                                <Icon iconNode={FileText} className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Markdown Length</p>
                                    <p className="text-2xl font-bold">{stats.markdown_length ? formatBytes(stats.markdown_length) : 'N/A'}</p>
                                </div>
                                <Icon iconNode={FileText} className="h-8 w-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Vector Sync</p>
                                    <p className="text-lg font-bold">{getStatusBadge(process.vector_sync_status)}</p>
                                </div>
                                <Icon iconNode={HardDrive} className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Processing Timeline */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Icon iconNode={Clock} className="h-5 w-5" />
                            Processing Overview
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {timeline.map((item, index) => (
                                <div key={index} className="flex items-start gap-4">
                                    <div className="flex-shrink-0 mt-1">
                                        {getTimelineIcon(item.status)}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{item.step}</span>
                                            {getStatusBadge(item.status)}
                                        </div>
                                        <p className="text-sm text-muted-foreground mt-1">{item.description}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Content Preview */}
                {(process.extracted_text || process.markdown_text) && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <Icon iconNode={FileText} className="h-5 w-5" />
                                    Content Preview
                                </CardTitle>
                                <div className="flex items-center gap-2">
                                    {process.extracted_text && (
                                        <Button
                                            variant={contentView === 'text' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setContentView('text')}
                                        >
                                            Extracted Text
                                        </Button>
                                    )}
                                    {process.markdown_text && (
                                        <Button
                                            variant={contentView === 'markdown' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setContentView('markdown')}
                                        >
                                            Markdown
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {contentView === 'text' && process.extracted_text && (
                                    <div>
                                        <div className="bg-muted rounded-md p-4 max-h-96 overflow-y-auto">
                                            <pre className="whitespace-pre-wrap text-sm leading-relaxed">
                                                {process.extracted_text.substring(0, 2000)}
                                                {process.extracted_text.length > 2000 && '...'}
                                            </pre>
                                        </div>
                                        <div className="mt-4 flex justify-center">
                                            <Button variant="outline" onClick={() => handleViewFullContent('text')}>
                                                <Icon iconNode={Eye} className="h-4 w-4 mr-2" />
                                                View Full Content
                                            </Button>
                                        </div>
                                    </div>
                                )}
                                
                                {contentView === 'markdown' && process.markdown_text && (
                                    <div>
                                        <div className="bg-muted rounded-md p-4 max-h-96 overflow-y-auto">
                                            <pre className="whitespace-pre-wrap text-sm leading-relaxed">
                                                {process.markdown_text.substring(0, 2000)}
                                                {process.markdown_text.length > 2000 && '...'}
                                            </pre>
                                        </div>
                                        <div className="mt-4 flex justify-center">
                                            <Button variant="outline" onClick={() => handleViewFullContent('markdown')}>
                                                <Icon iconNode={Eye} className="h-4 w-4 mr-2" />
                                                View Full Content
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Metadata */}
                {process.metadata && Object.keys(process.metadata).length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Icon iconNode={FileText} className="h-5 w-5" />
                                Document Metadata
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {Object.entries(process.metadata).map(([key, value]) => (
                                    <div key={key} className="flex items-center justify-between py-2 border-b last:border-b-0">
                                        <span className="font-medium capitalize">{key.replace('_', ' ')}</span>
                                        <span className="text-sm text-muted-foreground">{String(value)}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Content Dialog */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-h-[80vh] sm:max-w-2xl lg:max-w-6xl overflow-y-auto">
                    {dialogContent && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{dialogContent.title}</DialogTitle>
                            </DialogHeader>
                            <div className="mt-4">
                                <div className="bg-muted rounded-md p-4 max-h-96 overflow-y-auto">
                                    <pre className="whitespace-pre-wrap text-sm leading-relaxed">
                                        {dialogContent.content}
                                    </pre>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
} 