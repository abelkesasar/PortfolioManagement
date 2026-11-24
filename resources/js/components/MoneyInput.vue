<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  modelValue?: string | number | null
  decimals?: boolean
  placeholder?: string
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

// "15000000.5" -> "15.000.000,5"
const toDisplay = (value: string | number | null | undefined) => {
  if (value === null || value === undefined || value === '') return ''
  let s = String(value)
  if (/e/i.test(s)) s = Number(s).toFixed(8).replace(/\.?0+$/, '')
  const [int, dec] = s.split('.')
  const intFmt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return dec !== undefined ? `${intFmt},${dec}` : intFmt
}

// "15.000.000,5" -> "15000000.5"
const toRaw = (display: string) => display.replace(/\./g, '').replace(',', '.').replace(/\.$/, '')

const display = ref(toDisplay(props.modelValue))

// Sinkron kalau nilai diubah dari luar (misal form di-reset atau "Jual semua")
watch(
  () => props.modelValue,
  (v) => {
    if (toRaw(display.value) !== String(v ?? '')) display.value = toDisplay(v)
  },
)

const onInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  const ie = e as InputEvent
  let value = input.value
  let caret = input.selectionStart ?? value.length

  // Backspace/Delete tepat di titik pemisah: hapus digit di sebelahnya
  const sameTokens = value.replace(/[^\d,]/g, '') === display.value.replace(/[^\d,]/g, '')
  if (sameTokens && ie.inputType === 'deleteContentBackward') {
    const left = value.slice(0, caret).replace(/[\d,](?=[^\d,]*$)/, '')
    value = left + value.slice(caret)
    caret = left.length
  } else if (sameTokens && ie.inputType === 'deleteContentForward') {
    value = value.slice(0, caret) + value.slice(caret).replace(/[\d,]/, '')
  }

  const tokensLeft = value.slice(0, caret).replace(/[^\d,]/g, '').length

  let cleaned = value.replace(/[^\d,]/g, '')
  if (!props.decimals) cleaned = cleaned.replace(/,/g, '')

  const [intPart, ...rest] = cleaned.split(',')
  const hasDec = rest.length > 0
  let int = intPart.replace(/^0+(?=\d)/, '')
  if (hasDec && int === '') int = '0'
  const raw = hasDec ? `${int}.${rest.join('')}` : int

  const formatted = toDisplay(raw)

  // Kembalikan posisi kursor setelah format ulang
  let pos = 0
  let seen = 0
  while (pos < formatted.length && seen < tokensLeft) {
    if (/[\d,]/.test(formatted[pos])) seen++
    pos++
  }

  display.value = formatted
  input.value = formatted
  input.setSelectionRange(pos, pos)
  emit('update:modelValue', toRaw(formatted))
}
</script>

<template>
  <input
    type="text"
    :inputmode="decimals ? 'decimal' : 'numeric'"
    :value="display"
    :placeholder="placeholder"
    autocomplete="off"
    @input="onInput"
  />
</template>