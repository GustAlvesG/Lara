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
</script>
</x-slot>
