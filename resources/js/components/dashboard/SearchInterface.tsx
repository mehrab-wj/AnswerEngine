import { Icon } from '@/components/icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useForm } from '@inertiajs/react';
import { Bot, Clock, Code2, Search, X } from 'lucide-react';

interface SearchResult {
  query: string;
  aiAnswer: string;
  sourceDocuments: Array<{
    title: string;
    content: string;
    content_type: string;
    source_url?: string;
    filename?: string;
    author?: string;
    user_id: string;
    similarity: number;
  }>;
  processingTime: number;
}

interface SearchInterfaceProps {
    searchResult?: SearchResult;
}

export function SearchInterface({ searchResult }: SearchInterfaceProps) {
    const form = useForm({
        query: '',
    });

    const handleSearch = () => {
        if (!form.data.query.trim()) return;

        form.post('/dashboard/search');
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
                            className="max-h-[200px] min-h-[100px] w-full resize-y rounded-md border border-input bg-background p-3 focus:border-ring focus:ring-2 focus:ring-ring focus:outline-none"
                            disabled={form.processing}
                        />
                        {form.data.query && (
                            <Button variant="ghost" size="sm" className="absolute top-2 right-2 h-6 w-6 p-0" onClick={handleClear}>
                                <Icon iconNode={X} className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    {form.errors.query && <div className="text-sm text-red-600 dark:text-red-400">{form.errors.query}</div>}

                    <div className="flex gap-2">
                        <Button onClick={handleSearch} disabled={!form.data.query.trim() || form.processing} className="flex-1 sm:flex-none">
                            {form.processing ? (
                                <>
                                    <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent" />
                                    Searching...
                                </>
                            ) : (
                                <>
                                    <Icon iconNode={Search} className="mr-2 h-4 w-4" />
                                    Search
                                </>
                            )}
                        </Button>
                        <Button variant="outline" onClick={handleClear} disabled={!form.data.query && !searchResult}>
                            Clear
                        </Button>
                    </div>
                </div>

                {/* Results */}
                {searchResult && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Icon iconNode={Clock} className="h-4 w-4" />
                            <span>Query: "{searchResult.query}"</span>
                            <span>•</span>
                            <span>Processing time: {searchResult.processingTime}s</span>
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {/* AI Answer Panel */}
                            <div>
                                <Card className="bg-blue-50/50 dark:bg-blue-950/20">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <Icon iconNode={Bot} className="h-5 w-5 text-blue-600" />
                                            AI Answer
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="prose prose-sm dark:prose-invert max-w-none">
                                            <p className="text-sm leading-relaxed whitespace-pre-wrap">{searchResult.aiAnswer}</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Source Documents Panel */}
                            <div>
                                <Card className="bg-gray-50/50 dark:bg-gray-950/20">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <Icon iconNode={Code2} className="h-5 w-5 text-gray-600" />
                                            Source Documents ({searchResult.sourceDocuments.length})
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="max-h-64 space-y-3 overflow-y-auto">
                                            {searchResult.sourceDocuments.map((result, index) => (
                                                <div key={index} className="rounded-lg border bg-white p-3 text-sm dark:bg-gray-900">
                                                    <div className="mb-2 flex items-start justify-between gap-2">
                                                        <h4 className="font-medium text-foreground">{result.title}</h4>
                                                        <span className="rounded bg-muted px-2 py-1 text-xs text-muted-foreground">
                                                            {(result.similarity * 100).toFixed(1)}%
                                                        </span>
                                                    </div>
                                                                                                         <p className="mb-2 line-clamp-2 text-muted-foreground">{result.content}</p>
                                                     <div className="text-xs text-muted-foreground">
                                                         {result.content_type === 'pdf' 
                                                             ? `PDF: ${result.filename || 'Unknown'}` 
                                                             : `Website: ${result.source_url || 'Unknown'}`
                                                         }
                                                     </div>
                                                </div>
                                            ))}
                                        </div>

                                        {/* Raw JSON Toggle */}
                                        <details className="mt-4">
                                            <summary className="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                                                View Raw JSON
                                            </summary>
                                            <pre className="mt-2 overflow-x-auto rounded-lg bg-muted p-3 text-xs">
                                                {JSON.stringify(searchResult.sourceDocuments, null, 2)}
                                            </pre>
                                        </details>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
