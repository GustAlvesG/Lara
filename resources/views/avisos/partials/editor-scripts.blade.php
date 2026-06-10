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
