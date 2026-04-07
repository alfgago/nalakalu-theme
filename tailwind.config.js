/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.{php,html,js}",                     
    "./assets/js/**/*.{js}",                 
    "./assets/css/**/*.{css}",               
    "./blocks/**/*.{php,html,js}",           
    "./template-parts/**/*.{php,html,js}",
    "./inc/**/*.{php,html}",     
  ],
  theme: {
    extend: {
      fontFamily: {
        'fraunces': ['"Fraunces 72pt"', 'serif'],
      },
      colors: {
        'brown': '#3D332B',
        'beige': '#D7C5B4',
      },
      fontSize: {
        // Converted to rem (1rem = 10px)
        'display': ['12.8rem', 'normal'], // 128px
        'heading-1': ['8.4rem', '9.6rem'], // 84px / 96px line-height
        'heading-2': ['6.4rem', '8.4rem'], // 64px / 84px line-height
      },
      fontWeight: {
        'light': '300',
        'normal': '400',
      },
      textTransform: {
        'uppercase': 'uppercase',
      }
    },
  },
  plugins: [
    function({ addUtilities }) {
      const newUtilities = {
        '.font-heading-display': {
          fontFamily: '"Fraunces 72pt", serif',
          fontSize: '12.8rem', // 128px
          fontWeight: '400',
          lineHeight: 'normal',
          textTransform: 'uppercase',
        },
        '.font-heading-1': {
          fontFamily: '"Fraunces 72pt", serif',
          fontSize: '8.4rem', // 84px
          fontWeight: '300',
          lineHeight: '9.6rem', // 96px
          textTransform: 'uppercase',
        },
        '.font-heading-2': {
          fontFamily: '"Fraunces 72pt", serif',
          fontSize: '6.4rem', // 64px
          fontWeight: '300',
          lineHeight: '8.4rem', // 84px
        },
        '.text-brown': {
          color: '#3D332B',
        },
        '.text-beige': {
          color: '#D7C5B4',
        },
        '.bg-brown': {
          backgroundColor: '#3D332B',
        },
        '.bg-beige': {
          backgroundColor: '#D7C5B4',
        },
        '.border-brown': {
          borderColor: '#3D332B',
        },
        '.border-beige': {
          borderColor: '#D7C5B4',
        },
      }
      addUtilities(newUtilities)
    }
  ],
}
