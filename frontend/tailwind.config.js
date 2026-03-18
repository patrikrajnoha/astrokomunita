/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: [
          'InterVariable',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Liberation Sans',
          'Helvetica',
          'Arial',
          'sans-serif',
        ],
      },
      colors: {
        // Brand palette — used as bg-app, text-vivid, bg-hover, etc.
        app:           '#151d28',
        surface:       '#1d2228',
        hover:         '#1c2736',
        vivid:         { DEFAULT: '#0F73FF', hover: '#0d65e6', active: '#0a5ecc' },
        muted:         '#ABB8C9',
        'secondary-btn': { DEFAULT: '#222E3F', hover: '#2a3a52' },
        danger:        { DEFAULT: '#EB2452', hover: '#f1456c' },
      },
    },
  },
  plugins: [],
}
