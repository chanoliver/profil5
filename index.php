<?php
// Připojení session pro práci s flash hláškami (PRG pattern)
session_start();

// Definice názvu souboru pro uložení dat
$file = 'profile.json';

// Zajištění, že soubor existuje. Pokud ne, vytvoříme prázdné pole.
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

// Načtení a dekódování dat
$json_data = file_get_contents($file);
$interests = json_decode($json_data, true);

// Pokud by soubor obsahoval poškozená data, inicializujeme znovu jako prázdné pole
if (!is_array($interests)) {
    $interests = [];
}

// Zpracování odeslaného formuláře (metoda POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        // Získání vstupu a odstranění prázdných znaků z okrajů
        $new_item = isset($_POST['interest']) ? trim($_POST['interest']) : '';

        // Kontrola, zda nebyl odeslán prázdný vstup
        if ($new_item === '') {
            $_SESSION['message'] = 'Pole nesmí být prázdné.';
            $_SESSION['msg_type'] = 'error';
        } else {
            // Kontrola duplicity (ignoruje velikost písmen)
            $lower_interests = array_map('mb_strtolower', $interests);
            
            if (in_array(mb_strtolower($new_item), $lower_interests)) {
                $_SESSION['message'] = 'Tento zájem už existuje.';
                $_SESSION['msg_type'] = 'error';
            } else {
                // Skutečné přidání do pole
                $interests[] = $new_item;
                $interests = array_values($interests); // Přečíslování indexů pole
                
                // Uložení zpět do souboru
                file_put_contents($file, json_encode($interests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $_SESSION['message'] = 'Zájem byl úspěšně přidán.';
                $_SESSION['msg_type'] = 'success';
            }
        }
    } elseif ($action === 'delete') {
        // Smazání záznamu dle indexu
        $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
        
        if (isset($interests[$index])) {
            unset($interests[$index]); // Odstranění položky z pole
            $interests = array_values($interests); // Důležité: Přečíslování indexů po smazání
            
            file_put_contents($file, json_encode($interests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $_SESSION['message'] = 'Zájem byl odstraněn.';
            $_SESSION['msg_type'] = 'success';
        }
    } elseif ($action === 'edit') {
        // Úprava existujícího záznamu
        $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
        $updated_item = isset($_POST['interest']) ? trim($_POST['interest']) : '';

        if (isset($interests[$index])) {
            if ($updated_item === '') {
                $_SESSION['message'] = 'Pole nesmí být prázdné.';
                $_SESSION['msg_type'] = 'error';
            } else {
                $lower_interests = array_map('mb_strtolower', $interests);
                unset($lower_interests[$index]); // Vyjmutí aktuálně upravovaného prvku kvůli kontrole
                
                if (in_array(mb_strtolower($updated_item), $lower_interests)) {
                    $_SESSION['message'] = 'Tento zájem už existuje.';
                    $_SESSION['msg_type'] = 'error';
                } else {
                    $interests[$index] = $updated_item;
                    $interests = array_values($interests); // Přečíslování jen pro jistotu
                    
                    file_put_contents($file, json_encode($interests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    $_SESSION['message'] = 'Zájem byl upraven.';
                    $_SESSION['msg_type'] = 'success';
                }
            }
        }
    }

    // PRG Pattern - Přesměrování zpět po provedení akce
    header("Location: index.php");
    exit;
}

// Detekce, zda se nemá otevřít editační formulář u konkrétní položky (načtení parametrů přes GET)
$edit_index = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Profil 5.0 – Správa zájmů</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Správa zájmů</h1>

        <!-- Výpis případné zprávy ze session (success / error) -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?= htmlspecialchars($_SESSION['msg_type']) ?>">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php 
                // Zpráva se po zobrazení jednorázově promaže (striktní PRG behavior)
                unset($_SESSION['message']);
                unset($_SESSION['msg_type']);
            ?>
        <?php endif; ?>

        <!-- Formulář pro přidání -> provádí POST operaci add -->
        <form method="POST" action="index.php" class="add-form">
            <input type="hidden" name="action" value="add">
            <input type="text" name="interest" placeholder="Zadejte nový zájem..." autofocus>
            <button type="submit" class="btn btn-primary">Přidat</button>
        </form>

        <?php if (!empty($interests)): ?>
            <ul class="interest-list">
                <!-- Procházení všech zájmů -->
                <?php foreach ($interests as $index => $interest): ?>
                    <li class="interest-item">
                        <?php if ($index === $edit_index): ?>
                            <!-- Inline formulář pro úpravu (post akce edit) -->
                            <form method="POST" action="index.php" class="edit-form">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="index" value="<?= $index ?>">
                                <input type="text" name="interest" value="<?= htmlspecialchars($interest) ?>">
                                <div class="actions">
                                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                    <a href="index.php" class="btn btn-secondary btn-sm">Zrušit</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Běžný výpis jednoho zájmu -->
                            <span class="interest-name"><?= htmlspecialchars($interest) ?></span>
                            <div class="actions">
                                <!-- Úprava mění GET parametr -->
                                <a href="index.php?edit=<?= $index ?>" class="btn btn-warning btn-sm">Upravit</a>
                                
                                <!-- Mazání provádí rovnou bezpečnou POST operaci delete -->
                                <form method="POST" action="index.php" class="inline-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="index" value="<?= $index ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">Zatím nebyly přidány žádné zájmy. Můžete začít přidávat.</p>
        <?php endif; ?>
    </div>
</body>
</html>
