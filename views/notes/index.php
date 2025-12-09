<?php require 'includes/header.php'; ?>
<div class="container">
    <h1>Gestion des Notes</h1>
    <form method="POST" action="notes.php?action=store">
        <div class="form-group">
            <label for="classe_id">Classe</label>
            <select name="classe_id" id="classe_id" class="form-control">
                <?php foreach ($classes as $classe): ?>
                    <option value="<?= $classe['id'] ?>"><?= $classe['nom'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="matiere_id">Matière</label>
            <select name="matiere_id" id="matiere_id" class="form-control">
                <?php foreach ($matieres as $matiere): ?>
                    <option value="<?= $matiere['id'] ?>"><?= $matiere['nom'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="periode">Période</label>
            <input type="text" name="periode" id="periode" class="form-control">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Sauvegarder</button>
    </form>
</div>
<?php require 'includes/footer.php'; ?>
