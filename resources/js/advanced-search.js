/**
 * Advanced search input: badges for constraints, columns and AND / OR
 * operators, a keyboard navigable dropdown and search keybindings.
 */
const blankGroup = (operator = null) => ({
    columns: [],
    constraint: null,
    value: '',
    operator,
})

export default function advancedSearch({
    groups,
    defaultConstraint,
    maxGroups,
    hasGrouping,
    openOnFocus,
    keybindings,
}) {
    return {
    groups: groups.length ? groups.map((group) => ({ ...blankGroup(), ...group })) : [blankGroup()],

    active: 0,

    open: false,

    highlighted: -1,

    isInfoOpen: false,

    init() {
        this.active = this.groups.length - 1

        // A finished search starts a new, empty group when the user types again.
        this.$watch('open', (open) => {
            if (! open) {
                this.highlighted = -1
            }
        })
    },

    get current() {
        return this.groups[this.active]
    },

    get hasSearch() {
        return this.groups.some((group) => (group.value ?? '').trim() !== '')
    },

    get canAddGroup() {
        return hasGrouping && (this.groups.length < maxGroups) && ((this.current.value ?? '').trim() !== '')
    },

    isDefaultConstraint(constraint) {
        return (constraint === null) || (constraint === defaultConstraint)
    },

    focus() {
        this.$nextTick(() => this.$refs.input?.focus())
    },

    onFocus() {
        if (openOnFocus) {
            this.open = true
        }
    },

    items() {
        if (! this.$refs.menu) {
            return []
        }

        return [...this.$refs.menu.querySelectorAll('[data-fdt-as-item]')].filter((element) => element.offsetParent !== null)
    },

    move(step) {
        if (! this.open) {
            this.open = true
            this.highlighted = 0

            return
        }

        const items = this.items()

        if (! items.length) {
            return
        }

        this.highlighted = (this.highlighted + step + items.length) % items.length
        items[this.highlighted]?.scrollIntoView({ block: 'nearest' })
    },

    isHighlighted(element) {
        return this.items().indexOf(element) === this.highlighted
    },

    enter() {
        const items = this.items()

        if (this.open && (this.highlighted >= 0) && items[this.highlighted]) {
            items[this.highlighted].click()

            return
        }

        this.submit()
    },

    backspace(event) {
        if ((this.current.value ?? '') !== '') {
            return
        }

        event.preventDefault()

        if (this.current.columns.length) {
            this.current.columns.pop()

            return
        }

        if (! this.isDefaultConstraint(this.current.constraint)) {
            this.current.constraint = null

            return
        }

        if (this.active > 0) {
            this.groups.splice(this.active, 1)
            this.active = this.groups.length - 1
        }
    },

    selectConstraint(constraint) {
        this.current.constraint = constraint
        this.focus()
    },

    toggleColumn(column) {
        const index = this.current.columns.indexOf(column)

        if (index === -1) {
            this.current.columns.push(column)
        } else {
            this.current.columns.splice(index, 1)
        }

        this.focus()
    },

    removeColumn(groupIndex, column) {
        const group = this.groups[groupIndex]
        group.columns = group.columns.filter((item) => item !== column)

        if (groupIndex !== this.active) {
            this.submit()
        }
    },

    addGroup(operator) {
        if (! this.canAddGroup) {
            if (this.active > 0) {
                this.current.operator = operator
            }

            return
        }

        this.groups.push(blankGroup(operator))
        this.active = this.groups.length - 1
        this.open = false
        this.focus()
    },

    toggleOperator(index) {
        const group = this.groups[index]
        group.operator = group.operator === 'or' ? 'and' : 'or'
        this.submit()
    },

    removeGroup(index) {
        this.groups.splice(index, 1)

        if (! this.groups.length) {
            this.groups.push(blankGroup())
        }

        this.active = this.groups.length - 1
        this.submit()
    },

    submit() {
        this.open = false

        this.$wire.setAdvancedSearch(
            this.groups
                .filter((group) => (group.value ?? '').trim() !== '')
                .map((group) => ({ ...group, value: group.value.trim() })),
        )
    },

    clear() {
        this.groups = [blankGroup()]
        this.active = 0
        this.open = false
        this.$wire.resetAdvancedSearch()
    },

    handleKeybinding(event) {
        if (! keybindings.length) {
            return
        }

        const key = (event.key ?? '').toLowerCase()

        const matches = keybindings.some((binding) => {
            const parts = binding.toLowerCase().split('+').map((part) => part.trim())
            const bindingKey = parts.pop()

            return (bindingKey === key)
                && (parts.includes('command') || parts.includes('cmd') || parts.includes('meta')) === event.metaKey
                && (parts.includes('ctrl') || parts.includes('control')) === event.ctrlKey
                && parts.includes('alt') === event.altKey
                && parts.includes('shift') === event.shiftKey
        })

        if (! matches) {
            return
        }

        event.preventDefault()
        this.$refs.input?.focus()
        this.open = true
    },
    }
}
