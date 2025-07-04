import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Icon } from '@/components/icon';
import { FileText, RefreshCw, Database, Search } from 'lucide-react';
import { type StatData } from './MockData';

interface StatCardsProps {
  stats: StatData;
}

export function StatCards({ stats }: StatCardsProps) {
  const statItems = [
    {
      title: 'Total Documents',
      value: stats.totalDocs,
      icon: FileText,
      description: 'PDFs and websites processed',
      color: 'text-blue-600 dark:text-blue-400'
    },
    {
      title: 'Processing',
      value: stats.processing,
      icon: RefreshCw,
      description: 'Currently being processed',
      color: 'text-orange-600 dark:text-orange-400'
    },
    {
      title: 'Vector Synced',
      value: stats.vectorSynced,
      icon: Database,
      description: 'Ready for search',
      color: 'text-green-600 dark:text-green-400'
    },
    {
      title: 'Total Queries',
      value: stats.totalQueries,
      icon: Search,
      description: 'Search queries performed',
      color: 'text-purple-600 dark:text-purple-400'
    }
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {statItems.map((item, index) => (
        <Card key={index} className="h-full">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              {item.title}
            </CardTitle>
            <Icon 
              iconNode={item.icon} 
              className={`h-6 w-6 ${item.color}`}
            />
          </CardHeader>
          <CardContent className="pt-0">
            <div className="text-2xl font-bold">{item.value}</div>
            <p className="text-xs text-muted-foreground mt-1">
              {item.description}
            </p>
          </CardContent>
        </Card>
      ))}
    </div>
  );
} 