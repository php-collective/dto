import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'PHP DTO',
  description: 'Framework-agnostic DTO library with code generation for PHP',

  base: '/dto/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/dto/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#7c3aed' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'PHP DTO - Code Generation for Data Transfer Objects' }],
    ['meta', { property: 'og:description', content: 'Framework-agnostic DTO library with code generation. Zero runtime overhead, perfect IDE support, and TypeScript generation.' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      { text: 'Reference', link: '/reference/cli', activeMatch: '/reference/' },
      { text: 'Playground', link: '/playground' },
      {
        text: 'Integrations',
        items: [
          { text: 'CakePHP', link: 'https://github.com/dereuromark/cakephp-dto' },
          { text: 'Laravel', link: 'https://github.com/php-collective/laravel-dto' },
          { text: 'Symfony', link: 'https://github.com/php-collective/symfony-dto' },
        ]
      },
      {
        text: 'Links',
        items: [
          { text: 'Sandbox', link: 'https://sandbox.dereuromark.de/sandbox/dto-examples' },
          { text: 'Changelog', link: 'https://github.com/php-collective/dto/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/php-collective/dto' },
          { text: 'Issues', link: 'https://github.com/php-collective/dto/issues' },
        ]
      }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/' },
            { text: 'Motivation', link: '/guide/motivation' },
            { text: 'Examples', link: '/guide/examples' },
          ]
        },
        {
          text: 'Configuration',
          items: [
            { text: 'Config Builder', link: '/guide/config-builder' },
            { text: 'Validation', link: '/guide/validation' },
            { text: 'Advanced Types', link: '/guide/advanced-types' },
            { text: 'Shaped Arrays', link: '/guide/shaped-arrays' },
            { text: 'Custom Casters', link: '/guide/custom-casters' },
            { text: 'Collection Adapters', link: '/guide/collection-adapters' },
          ]
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Advanced Patterns', link: '/guide/advanced-patterns' },
            { text: 'Framework Integration', link: '/guide/framework-integration' },
            { text: 'Separating Generated Code', link: '/guide/separating-generated-code' },
            { text: 'Design Decisions', link: '/guide/design-decisions' },
          ]
        },
        {
          text: 'Operations',
          items: [
            { text: 'Performance', link: '/guide/performance' },
            { text: 'Testing', link: '/guide/testing' },
            { text: 'Troubleshooting', link: '/guide/troubleshooting' },
            { text: 'Migration', link: '/guide/migration' },
          ]
        }
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'CLI Reference', link: '/reference/cli' },
            { text: 'TypeScript Generation', link: '/reference/typescript' },
            { text: 'Schema Importer', link: '/reference/importer' },
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/php-collective/dto' }
    ],

    editLink: {
      pattern: 'https://github.com/php-collective/dto/edit/master/docs/:path',
      text: 'Edit this page on GitHub'
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © PHP Collective'
    },

    search: {
      provider: 'local'
    },

    outline: {
      level: [2, 3]
    }
  },

  markdown: {
    theme: {
      light: 'github-light',
      dark: 'github-dark'
    }
  }
})
