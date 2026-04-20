/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./*.html", "./*.js"],
    darkMode: 'class',
    safelist: [
        // Fondo de página
        'bg-[#2c1608]',
        // Fondo de cartas
        'bg-[#4a2010]',
        // Fondo de pastilla de avatares
        'bg-[#6b3020]',
        // Naranja primario (borde, texto, botón)
        'border-[#F47E24]',
        'border-l-4',
        'text-[#F47E24]',
        'hover:bg-[#F47E24]',
        'hover:text-[#F47E24]',
        'hover:text-white',
        'group-hover:text-[#F47E24]',
        // Navbar marrón
        'bg-[#55301c]',
        'text-[#55301c]',
        'hover:bg-[#F47E24]',
    ],
    theme: {
        extend: {}
    },
    plugins: [],
}
