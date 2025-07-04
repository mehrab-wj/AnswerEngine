import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { StatCards } from '@/components/dashboard/StatCards';
import { SearchInterface } from '@/components/dashboard/SearchInterface';
import { AddSourcesSection } from '@/components/dashboard/AddSourcesSection';
import { ProcessingTable } from '@/components/dashboard/ProcessingTable';
import { FlashMessages } from '@/components/dashboard/FlashMessages';
import { type StatData, type ProcessingItem } from '@/components/dashboard/MockData';

interface DashboardProps {
    stats: StatData;
    processingData: ProcessingItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ stats, processingData }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 overflow-x-auto">
                {/* Flash Messages */}
                <FlashMessages />
                
                {/* Stats Cards */}
                <StatCards stats={stats} />

                {/* Search Interface */}
                <SearchInterface />

                {/* Add Sources Section */}
                <AddSourcesSection />

                {/* Processing Table */}
                <ProcessingTable data={processingData} />
            </div>
        </AppLayout>
    );
}
