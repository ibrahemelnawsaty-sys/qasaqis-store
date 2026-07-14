import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // الوضع الليلي يُفعَّل عبر السمة data-theme="dark" على عنصر <html> (يتحكم فيها زر التبديل + تفضيل النظام)
    darkMode: ['selector', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Filament/**/*.php',
        './app/View/**/*.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                // خط عربي واحد أساسي مع أوزان محدودة — يُحمَّل محليًا (subset) لخِفّة الأداء
                sans: ['"Tajawal"', '"Cairo"', '"Segoe UI"', '"Noto Sans Arabic"', 'Tahoma', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // ألوان هوية «قصص أطفال» المستخرجة من الشعار والتصميم المعتمد
                brand: {
                    DEFAULT: '#6E2FB0',
                    deep: '#54228A',
                    soft: '#F0E6FA',
                },
                candy: {
                    pink: '#EC4E96',
                    orange: '#FF8A2A',
                    gold: '#FFC23C',
                    teal: '#12B3A6',
                    sky: '#4FB0E8',
                },
                cream: {
                    DEFAULT: '#FFF6EA',
                    100: '#FFEFDC',
                },
                ink: {
                    DEFAULT: '#372A46',
                    soft: '#6E6280',
                    faint: '#A99FB6',
                },
            },
            borderRadius: {
                pill: '999px',
            },
        },
    },
    plugins: [],
};
