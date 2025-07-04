import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { StatCards } from '@/components/dashboard/StatCards';
import { SearchInterface } from '@/components/dashboard/SearchInterface';
import { AddSourcesSection } from '@/components/dashboard/AddSourcesSection';
import { ProcessingTable } from '@/components/dashboard/ProcessingTable';
import { mockStats, mockProcessingData } from '@/components/dashboard/MockData';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 overflow-x-auto">
                {/* Stats Cards */}
                <StatCards stats={mockStats} />

                {/* Search Interface */}
                <SearchInterface />

                {/* Add Sources Section */}
                <AddSourcesSection />

                {/* Processing Table */}
                <ProcessingTable data={mockProcessingData} />
            </div>
        </AppLayout>
    );
}
