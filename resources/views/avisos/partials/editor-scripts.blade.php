<x-slot name="js">
<script>
    function editorCmd(cmd) {
        document.execCommand(cmd, false, null);
        document.getElementById('aviso-editor').focus();
    }

    document.getElementById('aviso-form').addEventListener('submit', function () {
        document.getElementById('aviso-content-input').value =
            document.getElementById('aviso-editor').innerHTML;
    });

    function lembretes(existing) {
        return {
            items: existing.length ? existing : [],
            add() {
                this.items.push({ remind_at: '' });
            },
            remove(index) {
                this.items.splice(index, 1);
            },
        };
    }

    function usersSelector(allUsers, selectedIds) {
        return {
            users: allUsers,
            selected: allUsers.filter(u => selectedIds.includes(u.id)),
            search: '',
            open: false,
            get filtered() {
                const q = this.search.toLowerCase();
                return this.users.filter(u =>
                    u.name.toLowerCase().includes(q) &&
                    !this.selected.find(s => s.id === u.id)
                );
            },
            add(user) {
                if (!this.selected.find(s => s.id === user.id)) {
                    this.selected.push(user);
                }
                this.search = '';
            },
            remove(user) {
                this.selected = this.selected.filter(s => s.id !== user.id);
            },
        };
    }

    function tagsInput(existing) {
        return {
            items: Array.isArray(existing) ? existing.slice() : [],
            draft: '',
            add() {
                const name = this.draft.trim().toLowerCase();
                this.draft = '';
                if (name === '') return;
                if (!this.items.includes(name)) this.items.push(name);
            },
            remove(index) {
                this.items.splice(index, 1);
            },
            removeLast() {
                if (this.items.length) this.items.pop();
            },
        };
    }
</script>
</x-slot>
