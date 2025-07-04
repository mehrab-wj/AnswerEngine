import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/icon';
import { Search, X, Bot, Code2, Clock } from 'lucide-react';
import { useForm } from '@inertiajs/react';

interface SearchResult {
  query: string;
  aiAnswer: string;
  sourceDocuments: Array<{
    title: string;
    content: string;
    source: string;
    similarity: number;
  }>;
  processingTime: number;
}

interface SearchInterfaceProps {
  searchResult?: SearchResult;
}

export function SearchInterface({ searchResult }: SearchInterfaceProps) {
  const form = useForm({
    query: ''
  });

  // Debug: Log search results
  console.log('SearchInterface searchResult:', searchResult);

  const handleSearch = () => {
    if (!form.data.query.trim()) return;

    console.log('Submitting search with query:', form.data.query);

    form.post('/dashboard/search', {
      onSuccess: (response) => {
        console.log('ON SUCCESS EVENT:', response);
        console.log('Search submitted successfully');
      },
      onError: (errors) => {
        console.log('Search submission errors:', errors);
      }
    });
  };

  const handleClear = () => {
    form.setData('query', '');
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      handleSearch();
    }
  };

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Icon iconNode={Search} className="h-5 w-5" />
          Search Knowledge Base
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Search Input */}
        <div className="space-y-3">
          <div className="relative">
            <textarea
              value={form.data.query}
              onChange={(e) => form.setData('query', e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Ask a question about your documents... (Ctrl+Enter to search)"
              className="w-full min-h-[100px] max-h-[200px] p-3 border border-input bg-background rounded-md resize-y focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring"
              disabled={form.processing}
            />
            {form.data.query && (
              <Button
                variant="ghost"
                size="sm"
                className="absolute top-2 right-2 h-6 w-6 p-0"
                onClick={handleClear}
              >
                <Icon iconNode={X} className="h-4 w-4" />
              </Button>
            )}
          </div>
          
          {form.errors.query && (
            <div className="text-sm text-red-600 dark:text-red-400">
              {form.errors.query}
            </div>
          )}
          
          <div className="flex gap-2">
            <Button
              onClick={handleSearch}
              disabled={!form.data.query.trim() || form.processing}
              className="flex-1 sm:flex-none"
            >
              {form.processing ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2" />
                  Searching...
                </>
              ) : (
                <>
                  <Icon iconNode={Search} className="h-4 w-4 mr-2" />
                  Search
                </>
              )}
            </Button>
            <Button
              variant="outline"
              onClick={handleClear}
              disabled={!form.data.query && !searchResult}
            >
              Clear
            </Button>
          </div>
        </div>

        {/* Debug Section */}
        {searchResult && (
          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h4 className="font-medium text-yellow-800 mb-2">Debug Info:</h4>
            <p className="text-sm text-yellow-700">Search Result Received: {JSON.stringify(searchResult, null, 2)}</p>
          </div>
        )}

        {/* Results */}
        {searchResult && (
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Icon iconNode={Clock} className="h-4 w-4" />
              <span>Query: "{searchResult.query}"</span>
              <span>•</span>
              <span>Processing time: {searchResult.processingTime}s</span>
            </div>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {/* AI Answer Panel */}
              <Card className="bg-blue-50/50 dark:bg-blue-950/20">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg flex items-center gap-2">
                    <Icon iconNode={Bot} className="h-5 w-5 text-blue-600" />
                    AI Answer
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="prose prose-sm max-w-none dark:prose-invert">
                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                      {searchResult.aiAnswer}
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Source Documents Panel */}
              <Card className="bg-gray-50/50 dark:bg-gray-950/20">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg flex items-center gap-2">
                    <Icon iconNode={Code2} className="h-5 w-5 text-gray-600" />
                    Source Documents ({searchResult.sourceDocuments.length})
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3 max-h-64 overflow-y-auto">
                    {searchResult.sourceDocuments.map((result, index) => (
                      <div key={index} className="p-3 bg-white dark:bg-gray-900 rounded-lg border text-sm">
                        <div className="flex items-start justify-between gap-2 mb-2">
                          <h4 className="font-medium text-foreground">{result.title}</h4>
                          <span className="text-xs text-muted-foreground bg-muted px-2 py-1 rounded">
                            {(result.similarity * 100).toFixed(1)}%
                          </span>
                        </div>
                        <p className="text-muted-foreground line-clamp-2 mb-2">
                          {result.content}
                        </p>
                        <div className="text-xs text-muted-foreground">
                          Source: {result.source}
                        </div>
                      </div>
                    ))}
                  </div>
                  
                  {/* Raw JSON Toggle */}
                  <details className="mt-4">
                    <summary className="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                      View Raw JSON
                    </summary>
                    <pre className="mt-2 p-3 bg-muted rounded-lg text-xs overflow-x-auto">
                      {JSON.stringify(searchResult.sourceDocuments, null, 2)}
                    </pre>
                  </details>
                </CardContent>
              </Card>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
} 