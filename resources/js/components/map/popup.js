// Popup construido con DOM y textContent: los datos del GeoJSON nunca se interpretan como HTML.

/** Solo http(s) y rutas relativas: descarta javascript:, data:, etc. */
const safeUrl = (u) => {
    if (typeof u !== 'string' || !u) return null
    try {
        const url = new URL(u, window.location.href)
        return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : null
    } catch { return null }
}

const el = (tag, className, text) => {
    const n = document.createElement(tag)
    if (className) n.className = className
    if (text) n.textContent = text
    return n
}

export function buildPopup(props = {}, openLabel = 'Open') {
    const root = el('div', 'max-w-60 space-y-1 text-sm')

    const image = safeUrl(props.image)
    if (image) {
        const img = el('img', 'w-full rounded')
        img.src = image
        img.alt = props.title ?? ''
        img.loading = 'lazy'
        root.append(img)
    }
    if (props.category) root.append(el('span', 'badge badge-sm', String(props.category)))
    if (props.title) root.append(el('p', 'font-semibold', String(props.title)))
    if (props.subtitle) root.append(el('p', 'opacity-70', String(props.subtitle)))

    const url = safeUrl(props.url)
    if (url) {
        const a = el('a', 'link link-primary', openLabel)
        a.href = url
        root.append(a)
    }
    return root
}
