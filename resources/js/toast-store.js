export function registerToastStore(Alpine) {
    Alpine.store('toast', {
        items: [],
        nextId: 1,

        push(type, message) {
            const id = this.nextId++;
            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), 4000);
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });
}
