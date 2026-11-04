// What the page of a browser test needs from Tiptap: the same objects an application imports to call registerRichText().
// esbuild turns this file into ONE module (the packages of Tiptap import many bare names that a browser cannot resolve by itself).
export { Editor } from '@tiptap/core'
export { default as StarterKit } from '@tiptap/starter-kit'
export { default as Link } from '@tiptap/extension-link'
export { default as Placeholder } from '@tiptap/extension-placeholder'
