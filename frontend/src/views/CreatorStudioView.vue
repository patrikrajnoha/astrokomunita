<template>
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <header class="rounded-2xl border border-slate-900/80 bg-slate-950/60 p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
        Studio
      </p>
      <h1 class="mt-2 text-3xl font-semibold text-slate-100">Creator Studio</h1>
      <p class="mt-2 text-sm text-slate-300">
        This is a placeholder workspace for future creator tools.
      </p>
    </header>

    <section class="grid gap-4 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-900/80 bg-slate-950/60 p-6">
        <h2 class="text-lg font-semibold text-slate-100">Drafts</h2>
        <p class="mt-1 text-sm text-slate-400">
          Manage your upcoming stories and posts.
        </p>
        <button
          type="button"
          class="mt-4 inline-flex items-center justify-center rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-sky-400/40 hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
        >
          Open drafts
        </button>
      </div>

      <div class="rounded-2xl border border-slate-900/80 bg-slate-950/60 p-6">
        <h2 class="text-lg font-semibold text-slate-100">Analytics</h2>
        <p class="mt-1 text-sm text-slate-400">
          A quick snapshot of your engagement (placeholder).
        </p>
        <div class="mt-4 h-24 rounded-xl border border-dashed border-slate-800"></div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-900/80 bg-slate-950/60 p-6">
      <h2 class="text-lg font-semibold text-slate-100">Need help?</h2>
      <p class="mt-1 text-sm text-slate-400">
        This button is a placeholder. It does not send a real message.
      </p>
      <button
        type="button"
        class="mt-4 inline-flex items-center justify-center rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-sky-400/40 hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
      >
        Contact
      </button>
    </section>
  </div>
</template>
