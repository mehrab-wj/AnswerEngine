import { AddSourcesSection } from '@/components/dashboard/AddSourcesSection';
import { FlashMessages } from '@/components/dashboard/FlashMessages';
import { type ProcessingItem, type StatData } from '@/components/dashboard/MockData';
import { ProcessingTable } from '@/components/dashboard/ProcessingTable';
import { SearchInterface } from '@/components/dashboard/SearchInterface';
import { StatCards } from '@/components/dashboard/StatCards';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePoll } from '@inertiajs/react';

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
    usePoll(2000);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
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
