<template>
  <section class="space-404">
    <div class="stars" aria-hidden="true">
      <span
        v-for="star in stars"
        :key="star.id"
        class="star"
        :style="star.style"
      ></span>
    </div>

    <div class="shooting-stars" aria-hidden="true">
      <span
        v-for="shootingStar in shootingStars"
        :key="shootingStar.id"
        class="shooting-star"
        :style="shootingStar.style"
      ></span>
    </div>

    <div class="center">
      <div class="circle circle--outer"></div>

      <div class="circle circle--inner">
        <div class="four four--1">4</div>
        <div class="four four--2">4</div>

        <div class="circle oh">
          <div class="astronaut">
            <div class="astronaut-backpack"></div>
            <div class="astronaut-head"></div>

            <div class="astronaut-antena antena--left">
              <div class="ear-down"></div>
              <div class="ear-up"></div>
              <div class="antena"></div>
              <div class="antena-tip"></div>
            </div>

            <div class="astronaut-antena antena--right">
              <div class="ear-down"></div>
              <div class="ear-up"></div>
              <div class="antena"></div>
              <div class="antena-tip"></div>
            </div>

            <div class="astronaut-helmet">
              <div class="astronaut-glass"></div>
              <div class="glow glow--1"></div>
              <div class="glow glow--2"></div>
            </div>

            <div class="astronaut-body"></div>
          </div>

          <svg class="astronaut-wire" viewBox="-9 -9 259 823" width="259" height="823" aria-hidden="true">
            <path
              d="M241 0c-24 54-30 113-78 148S63 159 27 215c-35 55 32 102 73 141s103 94 98 166c-6 97-169 66-192 157-10 43-8 84 9 126"
              fill="none"
              stroke-width="6"
              stroke="#fff"
            />
          </svg>

          <div class="circle planet">
            <div class="planet__surface">
              <div class="craters">
                <div class="crater crater--1"></div>
                <div class="crater crater--2"></div>
                <div class="crater crater--3"></div>
                <div class="crater crater--4"></div>
                <div class="crater crater--5"></div>
                <div class="crater crater--6"></div>
              </div>
            </div>
          </div>

          <div class="astronaut-hands">
            <div class="astronaut-hand hand--left">
              <svg width="35" height="75" aria-hidden="true">
                <path
                  d="M30.23 17.209c-7.925 5.118-11.657 12.786-11.226 22.975-7.113.934-12.948 4.345-18.44 5.117C-1.951 26.539 3.92 9.346 18.635 1.369 30.66-4.39 39.53 9.398 30.23 17.209z"
                  fill="#D2D2D2"
                />
                <g fill="none" stroke="#999" stroke-linecap="round">
                  <path d="M11.78 6.977c7.983.129 13.547 3.968 16.308 11.111M4.67 17.161c7.307-.379 13.1 1.924 17.93 6.94" />
                  <path d="M.816 31.334c6.439-2.441 12.295-1.746 18.149 2.488" stroke-linejoin="round" />
                </g>
                <g fill="#fff">
                  <path d="M7.721 37.171c5.875-1.994 12.264 1.156 14.258 7.031l1.218 3.588c1.995 5.875-1.156 12.264-7.03 14.258-5.875 1.995-12.264-1.156-14.259-7.031L.69 51.429c-1.994-5.875 1.156-12.263 7.031-14.258z" />
                  <path d="M7.829 38.159c5.794-1.967 12.094 1.14 14.061 6.934l5.044 14.855c1.967 5.794-1.14 12.095-6.934 14.062-5.794 1.967-12.095-1.14-14.062-6.934L.895 52.221c-1.967-5.794 1.14-12.095 6.934-14.062z" />
                  <path d="M16.863 39.472l12.879 7.384a3.876 3.876 0 0 1 1.433 5.287 3.875 3.875 0 0 1-5.286 1.433l-12.878-7.384a3.874 3.874 0 0 1-1.434-5.286 3.874 3.874 0 0 1 5.286-1.434z" />
                </g>
              </svg>
            </div>

            <div class="astronaut-hand hand--right">
              <svg width="35" height="75" aria-hidden="true">
                <path
                  d="M30.23 17.209c-7.925 5.118-11.657 12.786-11.226 22.975-7.113.934-12.948 4.345-18.44 5.117C-1.951 26.539 3.92 9.346 18.635 1.369 30.66-4.39 39.53 9.398 30.23 17.209z"
                  fill="#D2D2D2"
                />
                <g fill="none" stroke="#999" stroke-linecap="round">
                  <path d="M11.78 6.977c7.983.129 13.547 3.968 16.308 11.111M4.67 17.161c7.307-.379 13.1 1.924 17.93 6.94" />
                  <path d="M.816 31.334c6.439-2.441 12.295-1.746 18.149 2.488" stroke-linejoin="round" />
                </g>
                <g fill="#fff">
                  <path d="M7.721 37.171c5.875-1.994 12.264 1.156 14.258 7.031l1.218 3.588c1.995 5.875-1.156 12.264-7.03 14.258-5.875 1.995-12.264-1.156-14.259-7.031L.69 51.429c-1.994-5.875 1.156-12.263 7.031-14.258z" />
                  <path d="M7.829 38.159c5.794-1.967 12.094 1.14 14.061 6.934l5.044 14.855c1.967 5.794-1.14 12.095-6.934 14.062-5.794 1.967-12.095-1.14-14.062-6.934L.895 52.221c-1.967-5.794 1.14-12.095 6.934-14.062z" />
                  <path d="M16.863 39.472l12.879 7.384a3.876 3.876 0 0 1 1.433 5.287 3.875 3.875 0 0 1-5.286 1.433l-12.878-7.384a3.874 3.874 0 0 1-1.434-5.286 3.874 3.874 0 0 1 5.286-1.434z" />
                </g>
              </svg>
            </div>
          </div>
        </div>

      </div>

      <div class="sorry">Ups! Stránka sa nenašla.</div>
    </div>
  </section>
</template>

<script>
// 404 stars + astronaut animation inspired by:
// https://codepen.io/Meerkap/pen/NWMJKRg
// Adapted for this app (Vue structure, deterministic stars, responsive tuning).
function seededRandom(seed) {
  const value = Math.sin(seed * 9999.91) * 10000
  return value - Math.floor(value)
}

function createStars(count) {
  const stars = []

  for (let i = 1; i <= count; i += 1) {
    const x = seededRandom(i * 1.37)
    const y = seededRandom(i * 2.17)
    const size = [1, 2, 3, 4][Math.floor(seededRandom(i * 3.31) * 4)]
    const delay = -(seededRandom(i * 4.13) * 4)

    stars.push({
      id: i,
      style: {
        left: `${(x * 100).toFixed(2)}%`,
        top: `${(y * 100).toFixed(2)}%`,
        '--star-size': `${size}px`,
        '--blink-delay': `${delay.toFixed(2)}s`,
      },
    })
  }

  return stars
}

function createShootingStars(count) {
  const shootingStars = []

  for (let i = 1; i <= count; i += 1) {
    const top = 8 + (seededRandom(i * 5.33) * 42)
    const left = 8 + (seededRandom(i * 6.11) * 58)
    const tail = 40 + (seededRandom(i * 6.91) * 36)
    const distance = 180 + (seededRandom(i * 7.73) * 240)
    const cycle = 7 + (seededRandom(i * 8.41) * 6)
    const delay = -(seededRandom(i * 9.07) * cycle)
    const angle = 24 + (seededRandom(i * 9.83) * 16)

    shootingStars.push({
      id: i,
      style: {
        '--shoot-top': `${top.toFixed(2)}%`,
        '--shoot-left': `${left.toFixed(2)}%`,
        '--shoot-tail': `${tail.toFixed(0)}px`,
        '--shoot-distance': `${distance.toFixed(0)}px`,
        '--shoot-cycle': `${cycle.toFixed(2)}s`,
        '--shoot-delay': `${delay.toFixed(2)}s`,
        '--shoot-angle': `${angle.toFixed(2)}deg`,
      },
    })
  }

  return shootingStars
}

export default {
  name: 'NotFoundView',
  data() {
    return {
      stars: createStars(80),
      shootingStars: createShootingStars(6),
    }
  },
}
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.space-404 {
  position: relative;
  width: 100%;
  min-height: 100vh;
  overflow: hidden;
  font-size: 12px;
  background: #151d28;
  font-family: 'Open Sans', sans-serif;
  letter-spacing: 0.2px;
  color: #fff;
}

.center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  transition: transform 0.5s;
  animation: center-appear 760ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
  z-index: 1;
}

.stars {
  position: absolute;
  inset: 0;
}

.shooting-stars {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 1;
}

.star {
  position: absolute;
  z-index: 1;
}

.star::before,
.star::after {
  position: absolute;
  content: '';
  background-color: #fff;
  border-radius: 10px;
  animation: blink 1.5s infinite;
  animation-delay: var(--blink-delay);
}

.star::before {
  top: calc(var(--star-size) / 2);
  left: calc(var(--star-size) / -2);
  width: calc(3 * var(--star-size));
  height: var(--star-size);
}

.star::after {
  top: calc(var(--star-size) / -2);
  left: calc(var(--star-size) / 2);
  width: var(--star-size);
  height: calc(3 * var(--star-size));
}

.shooting-star {
  position: absolute;
  top: var(--shoot-top);
  left: var(--shoot-left);
  width: var(--shoot-tail);
  height: 2px;
  border-radius: 999px;
  opacity: 0;
  background: linear-gradient(90deg, rgb(255 255 255 / 0), rgb(255 255 255 / 0.98));
  filter: drop-shadow(0 0 6px rgb(255 255 255 / 0.42));
  transform-origin: left center;
  animation: shooting-star var(--shoot-cycle) linear infinite;
  animation-delay: var(--shoot-delay);
}

.shooting-star::after {
  content: '';
  position: absolute;
  right: -2px;
  top: 50%;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgb(255 255 255 / 0.92);
  transform: translateY(-50%);
}

.circle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border-radius: 50%;
}

.circle--outer {
  width: 550px;
  height: 550px;
  background: #151d28;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 0 20px -8px #000;
}

.circle--inner {
  width: 355px;
  height: 355px;
  background: #151d28;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 0 20px -8px #000;
}

.four {
  position: absolute;
  font-size: 150px;
  font-weight: 900;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
  user-select: none;
}

.four--1 {
  top: 100px;
  left: 30px;
  z-index: 1;
}

.four--2 {
  top: 100px;
  left: 230px;
  z-index: 3;
}

.oh {
  transition: all 0.1s linear;
  animation: floating 4s linear infinite;
  z-index: 2;
}

.astronaut {
  position: absolute;
  top: -115px;
  left: -30px;
  transform: rotate(17deg);
}

.astronaut-backpack {
  width: 100px;
  height: 100px;
  background-color: #e3e3e3;
  border-radius: 10px;
}

.antena--left {
  left: 10px;
}

.antena--right {
  right: 10px;
}

.astronaut-antena {
  position: absolute;
  top: -7px;
}

.ear-down {
  position: absolute;
  bottom: -5px;
  width: 15px;
  height: 20px;
  border-radius: 5px;
  background-color: #afafaf;
  z-index: 1;
}

.ear-up {
  position: relative;
  width: 15px;
  height: 20px;
  border-radius: 5px;
  background-color: #c3c3c3;
  z-index: 2;
}

.antena {
  position: absolute;
  top: -29px;
  width: 2px;
  height: 30px;
  border-radius: 5px;
  background-color: #c3c3c3;
}

.antena-tip {
  position: absolute;
  top: -33px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #c3c3c3;
}

.antena--left .antena {
  left: 5px;
}

.antena--right .antena {
  right: 5px;
}

.antena--left .antena-tip {
  left: 3px;
}

.antena--right .antena-tip {
  right: 3px;
}

.astronaut-helmet {
  position: absolute;
  top: -30px;
  left: 50%;
  width: 66px;
  height: 66px;
  border-radius: 50%;
  background-color: #fff;
  box-shadow: 0 5px 1px -3px #808080;
  transform: translateX(-50%);
  z-index: 3;
  overflow: hidden;
}

.astronaut-glass {
  position: absolute;
  top: 13px;
  left: 5px;
  width: 56px;
  height: 56px;
  background: #00202e;
  border-radius: 10px;
}

.glow {
  position: absolute;
  border-radius: 50%;
  background-color: #334d57;
}

.glow--1 {
  top: 25px;
  left: 15px;
  width: 17px;
  height: 17px;
}

.glow--2 {
  top: 44px;
  left: 13px;
  width: 7px;
  height: 7px;
}

.astronaut-body {
  position: absolute;
  top: 30px;
  left: 50%;
  width: 50px;
  height: 50px;
  background-color: #c3c3c3;
  transform: translateX(-50%);
}

.astronaut-wire {
  position: absolute;
  top: -40px;
  left: -260px;
  transform-origin: 100% 0;
  animation: wire-sway 4.6s ease-in-out infinite;
}

.planet {
  background-color: #ff4980;
  box-shadow: inset -6px -10px 0 1px #cc3b6b, 0 2px 10px rgba(0, 0, 0, 0.4);
  width: 155px;
  height: 155px;
  overflow: hidden;
}

.planet__surface {
  width: 100%;
  height: 100%;
  transform-origin: 50% 50%;
  animation: planet-rotate 18s linear infinite;
}

.craters {
  position: relative;
  width: 100%;
  height: 100%;
}

.crater {
  position: absolute;
  border-radius: 50%;
  background-color: #c93667;
}

.crater--1 {
  width: 35px;
  height: 35px;
  top: 30px;
  left: 25px;
}

.crater--2 {
  width: 23px;
  height: 23px;
  top: 20px;
  left: 93px;
}

.crater--3 {
  width: 17px;
  height: 17px;
  top: 77px;
  left: 17px;
}

.crater--4 {
  width: 10px;
  height: 10px;
  top: 90px;
  left: 80px;
}

.crater--5 {
  width: 25px;
  height: 25px;
  top: 115px;
  left: 50px;
}

.crater--6 {
  width: 13px;
  height: 13px;
  top: 118px;
  left: 105px;
}

.astronaut-hands {
  position: absolute;
  top: -89px;
  left: -23px;
  width: 100px;
  transform: rotate(17deg);
  z-index: 3;
}

.astronaut-hand {
  position: absolute;
}

.hand--left {
  left: -3px;
}

.hand--right {
  transform: scaleX(-1);
  right: -3px;
}

.sorry {
  position: absolute;
  top: 210px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 15px;
  max-width: min(90vw, 320px);
  white-space: normal;
  text-wrap: balance;
  line-height: 1.25;
  text-align: center;
  animation: sorry-appear 760ms ease-out 280ms both;
}

@keyframes center-appear {
  from {
    opacity: 0;
    filter: blur(6px);
  }

  to {
    opacity: 1;
    filter: blur(0);
  }
}

@keyframes blink {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }

  50% {
    transform: scale(0.4);
    opacity: 0.5;
  }
}

@keyframes shooting-star {
  0%,
  84% {
    opacity: 0;
    transform: translate3d(0, 0, 0) rotate(var(--shoot-angle)) scaleX(0.6);
  }

  87% {
    opacity: 0.95;
    transform: translate3d(0, 0, 0) rotate(var(--shoot-angle)) scaleX(1);
  }

  96% {
    opacity: 0.75;
    transform: translate3d(var(--shoot-distance), calc(var(--shoot-distance) * 0.42), 0) rotate(var(--shoot-angle)) scaleX(1);
  }

  100% {
    opacity: 0;
    transform: translate3d(calc(var(--shoot-distance) * 1.12), calc(var(--shoot-distance) * 0.48), 0) rotate(var(--shoot-angle)) scaleX(0.82);
  }
}

@keyframes floating {
  0%,
  100% {
    transform: translate(0, 0) rotate(0deg);
  }

  50% {
    transform: translate(0, -6px) rotate(1deg);
  }
}

@keyframes wire-sway {
  0%,
  100% {
    transform: rotate(0deg);
  }

  50% {
    transform: rotate(-2deg);
  }
}

@keyframes planet-rotate {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@keyframes sorry-appear {
  from {
    opacity: 0;
    transform: translate(-50%, 8px);
  }

  to {
    opacity: 1;
    transform: translate(-50%, 0);
  }
}

@media screen and (max-height: 600px) {
  .center {
    transform: translate(-50%, -50%) scale(0.75);
  }
}

@media screen and (max-width: 560px) {
  .center {
    transform: translate(-50%, -50%) scale(0.82);
  }
}

@media screen and (max-width: 400px) {
  .center {
    transform: translate(-50%, -50%) scale(0.6);
  }
}
</style>
