import { useEffect, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Icon } from '@/components/icon';
import { CheckCircle, XCircle, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';

interface FlashData {
  success?: string;
  error?: string;
}

export function FlashMessages() {
  const { props } = usePage<{ flash?: FlashData }>();
  const [messages, setMessages] = useState<FlashData>({});

  useEffect(() => {
    if (props.flash) {
      setMessages(props.flash);
      
      // Auto-hide success messages after 5 seconds
      if (props.flash.success) {
        const timer = setTimeout(() => {
          setMessages(prev => ({ ...prev, success: undefined }));
        }, 5000);
        
        return () => clearTimeout(timer);
      }
    }
  }, [props.flash]);

  const dismissMessage = (type: 'success' | 'error') => {
    setMessages(prev => ({ ...prev, [type]: undefined }));
  };

  if (!messages.success && !messages.error) {
    return null;
  }

  return (
    <div className="space-y-3 mb-4">
      {messages.success && (
        <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20">
          <Icon iconNode={CheckCircle} className="h-4 w-4 text-green-600" />
          <AlertDescription className="text-green-800 dark:text-green-300 flex items-center justify-between">
            <span>{messages.success}</span>
            <Button
              variant="ghost"
              size="sm"
              className="h-6 w-6 p-0 text-green-600 hover:text-green-700"
              onClick={() => dismissMessage('success')}
            >
              <Icon iconNode={X} className="h-4 w-4" />
            </Button>
          </AlertDescription>
        </Alert>
      )}
      
      {messages.error && (
        <Alert className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20">
          <Icon iconNode={XCircle} className="h-4 w-4 text-red-600" />
          <AlertDescription className="text-red-800 dark:text-red-300 flex items-center justify-between">
            <span>{messages.error}</span>
            <Button
              variant="ghost"
              size="sm"
              className="h-6 w-6 p-0 text-red-600 hover:text-red-700"
              onClick={() => dismissMessage('error')}
            >
              <Icon iconNode={X} className="h-4 w-4" />
            </Button>
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
} 