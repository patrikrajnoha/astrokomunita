<template>
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <header class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--color-text-secondary)]">
        Studio
      </p>
      <h1 class="mt-2 text-3xl font-semibold text-[var(--color-surface)]">Tvorive studio</h1>
      <p class="mt-2 text-sm text-[var(--color-text-secondary)]">
        Toto je docasny priestor pre buduce nastroje pre tvorcov.
      </p>
    </header>

    <section class="grid gap-4 md:grid-cols-2">
      <div class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
        <h2 class="text-lg font-semibold text-[var(--color-surface)]">Koncepty</h2>
        <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
          Spravujte pripravovane pribehy a prispevky.
        </p>
        <button
          type="button"
          class="mt-4 inline-flex items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.4)] hover:bg-[var(--color-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
        >
          Otvorit koncepty
        </button>
      </div>

      <div class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
        <h2 class="text-lg font-semibold text-[var(--color-surface)]">Analytika</h2>
        <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
          Rychly prehlad vasej aktivity (docasny obsah).
        </p>
        <div class="mt-4 h-24 rounded-xl border border-dashed border-[color:rgb(var(--color-text-secondary-rgb)/0.3)]"></div>
      </div>
    </section>

    <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-6">
      <h2 class="text-lg font-semibold text-[var(--color-surface)]">Potrebujete pomoc?</h2>
      <p class="mt-1 text-sm text-[var(--color-text-secondary)]">
        Toto tlacidlo je docasne. Neodosiela skutocnu spravu.
      </p>
      <button
        type="button"
        class="mt-4 inline-flex items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.7)] px-4 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.4)] hover:bg-[var(--color-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
      >
        Kontakt
      </button>
    </section>
  </div>
</template>
