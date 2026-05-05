// resources/js/types/index.ts

export interface Currency {
    id: number
    code: string
    name: string
    symbol?: string | null
}

export interface User {
    id: number
    name: string
    email: string
}

export type ReportStatus = 'pending' | 'processing' | 'completed' | 'failed'

export interface ReportDataPoint {
    date: string
    rate: number | null
}

export interface ReportListItem {
    id: number
    currency: Currency
    range: string
    range_label: string
    interval: string
    status: ReportStatus
    data_source: string | null
    created_at: string
    completed_at: string | null
}

export interface ReportDetail {
    id: number
    currency: Currency
    range: string
    range_label: string
    interval: string
    status: ReportStatus
    data_source: string | null
    completed_at: string | null
    results: ReportDataPoint[]
}

export interface RangePair {
    range: string
    interval: string
    label: string
}

export interface PageProps {
    auth: { user: User }
    flash: {
        success?: string
        info?: string
        error?: string
    }
    [key: string]: unknown
}
