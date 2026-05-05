import { ref } from 'vue'
import axios, { type AxiosResponse } from 'axios'
import api from '@/lib/axios'

export function useApi() {
    const loading = ref(false)
    const error = ref<string | null>(null)

    async function request<T>(fn: (client: typeof api) => Promise<AxiosResponse<T>>): Promise<T | null> {
        loading.value = true
        error.value = null

        try {
            const res = await fn(api)
            return res.data
        } catch (e) {
            error.value = axios.isAxiosError(e)
                ? (e.response?.data?.message ?? 'Something went wrong.')
                : 'Unexpected error.'
            return null
        } finally {
            loading.value = false
        }
    }

    return { loading, error, request }
}
