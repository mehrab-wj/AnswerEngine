import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/icon';
import { FileText, Globe, Calendar, Clock, HardDrive, Eye, Trash2 } from 'lucide-react';
import { type ProcessingItem } from './MockData';

interface ProcessingTableProps {
  data: ProcessingItem[];
}

type FilterType = 'all' | 'pdf' | 'website';

export function ProcessingTable({ data }: ProcessingTableProps) {
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');

  const filteredData = data.filter(item => {
    if (activeFilter === 'all') return true;
    return item.type === activeFilter;
  });

  const getStatusBadge = (status: ProcessingItem['status']) => {
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
        return <Badge variant="outline">Unknown</Badge>;
    }
  };

  const getVectorSyncBadge = (status: ProcessingItem['vectorSync']) => {
    switch (status) {
      case 'synced':
        return <Badge variant="secondary" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Synced</Badge>;
      case 'pending':
        return <Badge variant="secondary" className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">Pending</Badge>;
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
      year: 'numeric'
    });
  };

  const filters = [
    { id: 'all', label: 'All', count: data.length },
    { id: 'pdf', label: 'PDFs', count: data.filter(item => item.type === 'pdf').length },
    { id: 'website', label: 'Websites', count: data.filter(item => item.type === 'website').length }
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Icon iconNode={FileText} className="h-5 w-5" />
          Processing Status
        </CardTitle>
        <div className="flex gap-2 mt-4">
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
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Source
                </th>
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Status
                </th>
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Vector Sync
                </th>
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Details
                </th>
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Created
                </th>
                <th className="text-left py-3 px-4 font-medium text-sm text-muted-foreground">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {filteredData.map((item) => (
                <tr key={item.id} className="border-b hover:bg-muted/50">
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-3">
                      <Icon 
                        iconNode={getTypeIcon(item.type)} 
                        className="h-4 w-4 text-muted-foreground flex-shrink-0"
                      />
                      <div className="min-w-0 flex-1">
                        <div className="font-medium truncate">{item.name}</div>
                        <div className="text-sm text-muted-foreground capitalize">
                          {item.type}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    {getStatusBadge(item.status)}
                  </td>
                  <td className="py-3 px-4">
                    {getVectorSyncBadge(item.vectorSync)}
                  </td>
                  <td className="py-3 px-4">
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
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                      <Icon iconNode={Calendar} className="h-3 w-3" />
                      {formatDate(item.createdAt)}
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={() => console.log('View details:', item.id)}
                      >
                        <Icon iconNode={Eye} className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-red-500 hover:text-red-700"
                        onClick={() => console.log('Delete:', item.id)}
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
            <div className="text-center py-8 text-muted-foreground">
              <FileText className="h-12 w-12 mx-auto mb-2 opacity-50" />
              <p>No {activeFilter === 'all' ? 'documents' : `${activeFilter}s`} found</p>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
} 