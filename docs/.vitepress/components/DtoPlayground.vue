<script setup lang="ts">
import { ref, computed } from 'vue'

const jsonInput = ref(`{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "address": {
    "street": "123 Main St",
    "city": "New York",
    "zipCode": "10001"
  },
  "roles": ["admin", "user"],
  "metadata": {
    "createdAt": "2024-01-15",
    "active": true
  }
}`)

const outputFormat = ref<'php' | 'xml' | 'yaml'>('php')
const dtoName = ref('Object')
const error = ref('')

interface FieldDef {
  name: string
  type: string
  isDto?: boolean
  isCollection?: boolean
}

interface DtoDef {
  name: string
  fields: FieldDef[]
}

function inferType(value: unknown): string {
  if (value === null) return 'mixed'
  if (typeof value === 'string') return 'string'
  if (typeof value === 'number') return Number.isInteger(value) ? 'int' : 'float'
  if (typeof value === 'boolean') return 'bool'
  if (Array.isArray(value)) {
    if (value.length > 0 && typeof value[0] === 'object' && value[0] !== null) {
      return 'object[]'
    }
    return 'array'
  }
  if (typeof value === 'object') return 'object'
  return 'mixed'
}

function toPascalCase(str: string): string {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function parseObject(obj: Record<string, unknown>, name: string, dtos: Map<string, DtoDef>): DtoDef {
  const fields: FieldDef[] = []

  for (const [key, value] of Object.entries(obj)) {
    const baseType = inferType(value)

    if (baseType === 'object' && value !== null) {
      const nestedName = toPascalCase(key)
      parseObject(value as Record<string, unknown>, nestedName, dtos)
      fields.push({ name: key, type: nestedName, isDto: true })
    } else if (baseType === 'object[]' && Array.isArray(value) && value.length > 0) {
      const nestedName = toPascalCase(key.replace(/s$/, ''))
      parseObject(value[0] as Record<string, unknown>, nestedName, dtos)
      fields.push({ name: key, type: nestedName + '[]', isDto: true, isCollection: true })
    } else {
      fields.push({ name: key, type: baseType })
    }
  }

  const dto: DtoDef = { name, fields }
  dtos.set(name, dto)
  return dto
}

function generatePhp(dtos: Map<string, DtoDef>): string {
  const lines = [
    '<?php',
    '',
    'use PhpCollective\\Dto\\Config\\Dto;',
    'use PhpCollective\\Dto\\Config\\Field;',
    'use PhpCollective\\Dto\\Config\\Schema;',
    '',
    'return Schema::create()',
  ]

  const dtoArray = Array.from(dtos.values())
  dtoArray.forEach((dto, i) => {
    const isLast = i === dtoArray.length - 1
    lines.push(`    ->dto(Dto::create('${dto.name}')->fields(`)
    dto.fields.forEach((field, j) => {
      const isLastField = j === dto.fields.length - 1
      let fieldLine = '        '

      if (field.isDto && field.isCollection) {
        const singularType = field.type.replace('[]', '')
        fieldLine += `Field::collection('${field.name}', '${singularType}')`
      } else if (field.isDto) {
        fieldLine += `Field::dto('${field.name}', '${field.type}')`
      } else {
        fieldLine += `Field::${field.type}('${field.name}')`
      }

      fieldLine += isLastField ? ',' : ','
      lines.push(fieldLine)
    })
    lines.push(`    ))${isLast ? '' : ''}`)
  })

  lines.push('    ->toArray();')

  return lines.join('\n')
}

function generateXml(dtos: Map<string, DtoDef>): string {
  const lines = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<dtos xmlns="https://github.com/php-collective/dto">',
  ]

  for (const dto of dtos.values()) {
    lines.push(`    <dto name="${dto.name}">`)
    for (const field of dto.fields) {
      if (field.isCollection) {
        const singularType = field.type.replace('[]', '')
        lines.push(`        <field name="${field.name}" type="${singularType}[]" collection="true"/>`)
      } else {
        lines.push(`        <field name="${field.name}" type="${field.type}"/>`)
      }
    }
    lines.push('    </dto>')
    lines.push('')
  }

  lines.pop() // Remove trailing empty line
  lines.push('</dtos>')

  return lines.join('\n')
}

function generateYaml(dtos: Map<string, DtoDef>): string {
  const lines: string[] = []

  for (const dto of dtos.values()) {
    lines.push(`${dto.name}:`)
    lines.push('  fields:')
    for (const field of dto.fields) {
      if (field.isCollection) {
        lines.push(`    ${field.name}:`)
        lines.push(`      type: ${field.type}`)
        lines.push('      collection: true')
      } else {
        lines.push(`    ${field.name}: ${field.type}`)
      }
    }
    lines.push('')
  }

  return lines.join('\n').trim()
}

const output = computed(() => {
  error.value = ''

  try {
    const parsed = JSON.parse(jsonInput.value)

    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
      error.value = 'Input must be a JSON object'
      return ''
    }

    const dtos = new Map<string, DtoDef>()
    parseObject(parsed, dtoName.value, dtos)

    switch (outputFormat.value) {
      case 'php': return generatePhp(dtos)
      case 'xml': return generateXml(dtos)
      case 'yaml': return generateYaml(dtos)
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Invalid JSON'
    return ''
  }
})

function copyOutput() {
  navigator.clipboard.writeText(output.value)
}
</script>

<template>
  <div class="playground">
    <div class="playground-header">
      <div class="controls">
        <label>
          DTO Name:
          <input v-model="dtoName" type="text" placeholder="Object" />
        </label>
        <label>
          Output:
          <select v-model="outputFormat">
            <option value="php">PHP Builder</option>
            <option value="xml">XML</option>
            <option value="yaml">YAML</option>
          </select>
        </label>
      </div>
    </div>

    <div class="playground-panels">
      <div class="panel">
        <div class="panel-header">
          <span>JSON Input</span>
        </div>
        <textarea
          v-model="jsonInput"
          class="code-input"
          spellcheck="false"
          placeholder="Paste your JSON here..."
        ></textarea>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span>DTO Configuration</span>
          <button @click="copyOutput" class="copy-btn" :disabled="!!error">
            Copy
          </button>
        </div>
        <div v-if="error" class="error">{{ error }}</div>
        <pre v-else class="code-output"><code>{{ output }}</code></pre>
      </div>
    </div>

    <p class="playground-note">
      This is a simplified preview. The actual
      <a href="/dto/reference/importer">Schema Importer</a>
      supports JSON Schema, OpenAPI, and more advanced type inference.
    </p>
  </div>
</template>

<style scoped>
.playground {
  border: 1px solid var(--vp-c-border);
  border-radius: 8px;
  overflow: hidden;
  margin: 1.5rem 0;
}

.playground-header {
  background: var(--vp-c-bg-soft);
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--vp-c-border);
}

.controls {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.controls label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.controls input,
.controls select {
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--vp-c-border);
  border-radius: 4px;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
  font-size: 0.875rem;
}

.controls input {
  width: 120px;
}

.playground-panels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 400px;
}

@media (max-width: 768px) {
  .playground-panels {
    grid-template-columns: 1fr;
  }
}

.panel {
  display: flex;
  flex-direction: column;
}

.panel:first-child {
  border-right: 1px solid var(--vp-c-border);
}

@media (max-width: 768px) {
  .panel:first-child {
    border-right: none;
    border-bottom: 1px solid var(--vp-c-border);
  }
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 1rem;
  background: var(--vp-c-bg-soft);
  border-bottom: 1px solid var(--vp-c-border);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--vp-c-text-2);
}

.copy-btn {
  padding: 0.25rem 0.75rem;
  background: var(--vp-c-brand-1);
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: opacity 0.2s;
}

.copy-btn:hover {
  opacity: 0.9;
}

.copy-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.code-input {
  flex: 1;
  padding: 1rem;
  border: none;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
  font-family: var(--vp-font-family-mono);
  font-size: 0.875rem;
  line-height: 1.6;
  resize: none;
}

.code-input:focus {
  outline: none;
}

.code-output {
  flex: 1;
  margin: 0;
  padding: 1rem;
  background: var(--vp-c-bg);
  overflow: auto;
}

.code-output code {
  font-family: var(--vp-font-family-mono);
  font-size: 0.875rem;
  line-height: 1.6;
  color: var(--vp-c-text-1);
}

.error {
  flex: 1;
  padding: 1rem;
  color: var(--vp-c-danger-1);
  font-family: var(--vp-font-family-mono);
  font-size: 0.875rem;
}

.playground-note {
  padding: 0.75rem 1rem;
  background: var(--vp-c-bg-soft);
  border-top: 1px solid var(--vp-c-border);
  font-size: 0.8125rem;
  color: var(--vp-c-text-2);
  margin: 0;
}

.playground-note a {
  color: var(--vp-c-brand-1);
}
</style>
