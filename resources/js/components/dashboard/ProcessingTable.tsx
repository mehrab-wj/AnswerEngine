import { Icon } from '@/components/icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { Calendar, Clock, Eye, FileText, Globe, HardDrive, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteConfirmDialog } from './DeleteConfirmDialog';
import { type ProcessingItem } from './MockData';

interface ProcessingTableProps {
    data: ProcessingItem[];
}

type FilterType = 'all' | 'pdf' | 'website';

export function ProcessingTable({ data }: ProcessingTableProps) {
    const [activeFilter, setActiveFilter] = useState<FilterType>('all');
    const [deleteDialog, setDeleteDialog] = useState<{
        isOpen: boolean;
        item: ProcessingItem | null;
    }>({ isOpen: false, item: null });
    const [isDeleting, setIsDeleting] = useState(false);

    const filteredData = data.filter((item) => {
        if (activeFilter === 'all') return true;
        return item.type === activeFilter;
    });

    const getStatusBadge = (status: ProcessingItem['status']) => {
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
                return <Badge variant="outline">Unknown ({status})</Badge>;
        }
    };

    const getVectorSyncBadge = (status: ProcessingItem['vectorSync']) => {
        switch (status) {
            case 'synced':
            case 'completed':
                return (
                    <Badge variant="secondary" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                        Synced
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
                    <Badge variant="secondary" className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        Pending
                    </Badge>
                );
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            default:
                return <Badge variant="outline">Unknown</Badge>;
        }
    };

    const getTypeIcon = (type: ProcessingItem['type']) => {
        return type === 'pdf' ? FileText : Globe;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const filters = [
        { id: 'all', label: 'All', count: data.length },
        { id: 'pdf', label: 'PDFs', count: data.filter((item) => item.type === 'pdf').length },
        { id: 'website', label: 'Websites', count: data.filter((item) => item.type === 'website').length },
    ];

    const handleDeleteClick = (item: ProcessingItem) => {
        setDeleteDialog({ isOpen: true, item });
    };

    const handleDeleteConfirm = (item: ProcessingItem) => {
        setIsDeleting(true);

        router.delete('/dashboard/source', {
            data: {
                id: item.id,
                type: item.type,
            },
            onSuccess: () => {
                setDeleteDialog({ isOpen: false, item: null });
                setIsDeleting(false);
                // Inertia will automatically refresh the page with new data and show flash messages
            },
            onError: () => {
                setIsDeleting(false);
                // Keep dialog open so user can retry
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleDeleteCancel = () => {
        setDeleteDialog({ isOpen: false, item: null });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Icon iconNode={FileText} className="h-5 w-5" />
                    Processing Status
                </CardTitle>
                <div className="mt-4 flex gap-2">
                    {filters.map((filter) => (
                        <Button
                            key={filter.id}
                            variant={activeFilter === filter.id ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setActiveFilter(filter.id as FilterType)}
                        >
                            {filter.label} ({filter.count})
                        </Button>
                    ))}
                </div>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b">
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Source</th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Status</th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Vector Sync</th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Details</th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Created</th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredData.map((item) => (
                                <tr key={`${item.type}-${item.id}`} className="border-b hover:bg-muted/50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <Icon iconNode={getTypeIcon(item.type)} className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate font-medium">{item.name}</div>
                                                <div className="text-sm text-muted-foreground capitalize">{item.type}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">{getStatusBadge(item.status)}</td>
                                    <td className="px-4 py-3">{getVectorSyncBadge(item.vectorSync)}</td>
                                    <td className="px-4 py-3">
                                        <div className="space-y-1">
                                            {item.size && (
                                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <Icon iconNode={HardDrive} className="h-3 w-3" />
                                                    {item.size}
                                                </div>
                                            )}
                                            {item.pages && (
                                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <Icon iconNode={FileText} className="h-3 w-3" />
                                                    {item.pages} pages
                                                </div>
                                            )}
                                            {item.processingTime && (
                                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <Icon iconNode={Clock} className="h-3 w-3" />
                                                    {item.processingTime}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                            <Icon iconNode={Calendar} className="h-3 w-3" />
                                            {formatDate(item.createdAt)}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1">
                                            <a href={`/process/${item.type}/${item.uuid}`}>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <Icon iconNode={Eye} className="h-4 w-4" />
                                                </Button>
                                            </a>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 w-8 p-0 text-red-500 hover:text-red-700"
                                                onClick={() => handleDeleteClick(item)}
                                            >
                                                <Icon iconNode={Trash2} className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {filteredData.length === 0 && (
                        <div className="py-8 text-center text-muted-foreground">
                            <FileText className="mx-auto mb-2 h-12 w-12 opacity-50" />
                            <p>No {activeFilter === 'all' ? 'documents' : `${activeFilter}s`} found</p>
                        </div>
                    )}
                </div>
            </CardContent>

            <DeleteConfirmDialog
                item={deleteDialog.item}
                isOpen={deleteDialog.isOpen}
                onClose={handleDeleteCancel}
                onConfirm={handleDeleteConfirm}
                isDeleting={isDeleting}
            />
        </Card>
    );
}
