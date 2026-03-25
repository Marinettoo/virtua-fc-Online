<div
    x-data="{
        submitted: false,
        alreadyOnList: false,
        loading: false,
        error: null,
        async joinWaitlist() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/api/waitlist', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: @js(auth()->user()->name),
                        email: @js(auth()->user()->email),
                    }),
                });

                if (response.status === 201) {
                    this.submitted = true;
                } else if (response.status === 200) {
                    this.alreadyOnList = true;
                } else {
                    const data = await response.json();
                    this.error = data.error || @js(__('waitlist.career_cta_error'));
                }
            } catch (e) {
                this.error = @js(__('waitlist.career_cta_error'));
            } finally {
                this.loading = false;
            }
        }
    }"
>
    {{-- Success state --}}
    <template x-if="submitted">
        <div class="rounded-lg bg-accent-green/10 border border-accent-green/20 p-6 text-center">
            <svg class="w-8 h-8 text-accent-green mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="text-sm font-semibold text-accent-green">{{ __('waitlist.career_cta_success') }}</p>
        </div>
    </template>

    {{-- Already on waitlist state --}}
    <template x-if="alreadyOnList">
        <div class="rounded-lg bg-accent-blue/10 border border-accent-blue/20 p-6 text-center">
            <svg class="w-8 h-8 text-accent-blue mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <p class="text-sm font-semibold text-accent-blue">{{ __('waitlist.career_cta_already') }}</p>
        </div>
    </template>

    {{-- Default CTA state --}}
    <template x-if="!submitted && !alreadyOnList">
        <div class="rounded-lg bg-surface-800 border border-border-default p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0">
                    <h4 class="font-heading font-semibold text-text-primary text-base tracking-wide">{{ __('waitlist.career_cta_title') }}</h4>
                    <p class="text-sm text-text-secondary mt-1">{{ __('waitlist.career_cta_description') }}</p>
                </div>
                <div class="shrink-0">
                    <button
                        @click="joinWaitlist()"
                        :disabled="loading"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-semibold rounded-lg bg-accent-blue/15 text-accent-blue border border-accent-blue/25 hover:bg-accent-blue/25 transition disabled:opacity-50"
                    >
                        <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="loading ? @js(__('waitlist.career_cta_loading')) : @js(__('waitlist.career_cta_button'))"></span>
                    </button>
                </div>
            </div>
            <p x-show="error" x-text="error" class="text-xs text-accent-red mt-3"></p>
        </div>
    </template>
</div>
