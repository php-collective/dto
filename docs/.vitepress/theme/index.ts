import DefaultTheme from 'vitepress/theme'
import DtoPlayground from '../components/DtoPlayground.vue'
import './custom.css'

export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.component('DtoPlayground', DtoPlayground)
  }
}
