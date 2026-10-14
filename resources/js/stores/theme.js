import { defineStore } from 'pinia'

export const useThemeStore = defineStore('theme', {
  state: () => {
    const saved = localStorage.getItem('dbm_theme')
    let isDark = true

    if (saved) {
      isDark = saved === 'dark'
    } else {
      isDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    }

    return {
      isDark,
    }
  },

  actions: {
    initTheme() {
      this.applyTheme()
    },

    toggleTheme() {
      this.isDark = !this.isDark
      this.applyTheme()
    },

    setDark(value) {
      this.isDark = value
      this.applyTheme()
    },

    applyTheme() {
      if (this.isDark) {
        document.documentElement.classList.add('dark')
        localStorage.setItem('dbm_theme', 'dark')
      } else {
        document.documentElement.classList.remove('dark')
        localStorage.setItem('dbm_theme', 'light')
      }
    },
  },
})
