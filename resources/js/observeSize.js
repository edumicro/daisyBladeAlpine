'use strict'

// ONE way for a component to find out how big its element is, when that depends on the layout.
//
// A component that measures its element (the canvas of the signature, the viewport of the flowchart, the container of the map) cannot
// measure once, when it starts: it may start INSIDE something that is hidden (a closed tab, a modal, a collapsed section, a step of a
// wizard that is not the current one), and there every size is 0 px. The size appears later, when the user shows it. A browser tells
// us with `ResizeObserver`, so a component never has to know where it lives:
//
//     const watch = observeSize(this.$refs.canvas, () => this.resize())     // in init()
//     watch.stop()                                                          // in destroy()
//
// tests/Browser/HiddenStartBrowserTest.php starts each of these components hidden, shows it, and checks that it works.

/**
 * Calls `onSize({ width, height })` every time the element has a real size (more than 0 px both ways) and that size is not the one
 * of the last call. A hidden element (0 px) says nothing; when it is shown, the first call comes; a window resize, a sidebar that
 * opens and anything else that changes the size makes the next one.
 *
 * Browsers without `ResizeObserver` (very old ones) fall back to the `resize` event of the window.
 *
 * @returns {{ stop: () => void }}
 */
export function observeSize(element, onSize) {
    let last = { width: 0, height: 0 }

    const check = () => {
        const { width, height } = element.getBoundingClientRect()
        const isHidden = width === 0 || height === 0
        if (isHidden || (width === last.width && height === last.height)) return
        last = { width, height }
        onSize(last)
    }

    if (typeof ResizeObserver === 'undefined') {
        window.addEventListener('resize', check)
        check()
        return { stop: () => window.removeEventListener('resize', check) }
    }

    const observer = new ResizeObserver(check)
    observer.observe(element)
    return { stop: () => observer.disconnect() }
}

/** Calls `onSize` once, the first time the element has a real size, and then stops watching. */
export function onceSized(element, onSize) {
    const watch = observeSize(element, size => {
        watch.stop()
        onSize(size)
    })
    return watch
}
