import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/icon';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { type ProcessingItem } from './MockData';

interface DeleteConfirmDialogProps {
  item: ProcessingItem | null;
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (item: ProcessingItem) => void;
  isDeleting: boolean;
}

export function DeleteConfirmDialog({ 
  item, 
  isOpen, 
  onClose, 
  onConfirm,
  isDeleting 
}: DeleteConfirmDialogProps) {
  if (!item) return null;

  const handleConfirm = () => {
    onConfirm(item);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Icon iconNode={AlertTriangle} className="h-5 w-5 text-red-500" />
            Delete {item.type === 'pdf' ? 'PDF Document' : 'Website'}
          </DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this {item.type === 'pdf' ? 'PDF document' : 'website'}? 
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        
        <div className="py-4">
          <div className="bg-muted rounded-lg p-4 space-y-2">
            <div className="font-medium text-sm">
              {item.type === 'pdf' ? 'File:' : 'URL:'} {item.name}
            </div>
            <div className="text-sm text-muted-foreground">
              Status: {item.status} • Created: {item.createdAt}
            </div>
            {item.type === 'pdf' && item.size && (
              <div className="text-sm text-muted-foreground">
                Size: {item.size} • Pages: {item.pages}
              </div>
            )}
            {item.type === 'website' && (
              <div className="text-sm text-muted-foreground">
                Pages scraped: {item.pages}
              </div>
            )}
          </div>
          
          <div className="mt-4 p-3 bg-red-50 dark:bg-red-950/20 rounded-lg border border-red-200 dark:border-red-800">
            <div className="flex items-start gap-2">
              <Icon iconNode={AlertTriangle} className="h-4 w-4 text-red-500 mt-0.5 flex-shrink-0" />
              <div className="text-sm text-red-700 dark:text-red-400">
                <strong>Warning:</strong> This will permanently delete the {item.type === 'pdf' ? 'PDF file' : 'scraped data'} 
                and remove it from your vector database. All associated search data will be lost.
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button 
            variant="outline" 
            onClick={onClose}
            disabled={isDeleting}
          >
            Cancel
          </Button>
          <Button 
            variant="destructive" 
            onClick={handleConfirm}
            disabled={isDeleting}
          >
            {isDeleting ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2" />
                Deleting...
              </>
            ) : (
              <>
                <Icon iconNode={Trash2} className="h-4 w-4 mr-2" />
                Delete {item.type === 'pdf' ? 'PDF' : 'Website'}
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
} 