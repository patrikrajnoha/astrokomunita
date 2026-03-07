<template>
  <main class="authSplit">
    <section class="authSplit__hero">
      <slot name="hero" />
    </section>
    <div class="authSplit__divider" aria-hidden="true"></div>
    <section class="authSplit__form">
      <slot />
    </section>
  </main>
</template>
