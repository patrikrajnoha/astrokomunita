<template>
  <main class="authSplit">
    <div class="authSplit__stars" aria-hidden="true">
      <span
        v-for="star in stars"
        :key="star.id"
        class="authSplit__star"
        :style="star.style"
      ></span>
    </div>

    <div class="authSplit__frame relative z-[1] mx-auto flex min-h-dvh w-full px-3 py-3 sm:px-5 sm:py-5 lg:px-8 lg:py-8">
      <div class="authSplit__grid grid w-full overflow-hidden rounded-[28px] lg:rounded-[36px]">
        <section class="authSplit__hero">
          <slot name="hero" />
        </section>

        <div class="authSplit__divider" aria-hidden="true"></div>

        <section class="authSplit__form">
          <div class="authSplit__panel">
            <slot />
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

<script setup>
function seededRandom(seed) {
  const value = Math.sin(seed * 9999.91) * 10000
  return value - Math.floor(value)
}

function createStars(count) {
  const generatedStars = []
  for (let i = 1; i <= count; i += 1) {
    const x = seededRandom(i * 1.37)
    const y = seededRandom(i * 2.17)
    const size = [1, 2, 3, 4][Math.floor(seededRandom(i * 3.31) * 4)]
    const delay = -(seededRandom(i * 4.13) * 4)
    generatedStars.push({
      id: i,
      style: {
        left: `${(x * 100).toFixed(2)}%`,
        top: `${(y * 100).toFixed(2)}%`,
        '--star-size': `${size}px`,
        '--blink-delay': `${delay.toFixed(2)}s`,
      },
    })
  }
  return generatedStars
}

const stars = createStars(80)
</script>
