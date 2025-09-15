<?php
/**
 * Reusable Form Snippets for FrozoFun Admin
 * Usage: include as needed in forms for consistent field rendering
 */

// Example: Item fields
function item_form_fields($item = []) { ?>
    <div>
        <label>Code:</label>
        <input type="text" name="code" value="<?= h($item['code'] ?? '') ?>" required>
    </div>
    <div>
        <label>Name:</label>
        <input type="text" name="name" value="<?= h($item['name'] ?? '') ?>" required>
    </div>
    <div>
        <label>Price per Unit:</label>
        <input type="number" name="price_per_unit" step="0.01" value="<?= h($item['price_per_unit'] ?? '') ?>" required>
    </div>
    <div>
        <label>Default Pack Size:</label>
        <input type="number" name="default_pack_size" value="<?= h($item['default_pack_size'] ?? '') ?>">
    </div>
    <div>
        <label>Category:</label>
        <select name="category_id">
            <!-- TODO: Populate options -->
        </select>
    </div>
    <div>
        <label>Image:</label>
        <input type="file" name="item_image">
        <?php if (!empty($item['image'])): ?>
            <img src="<?= h($item['image']) ?>" alt="" height="40">
        <?php endif; ?>
    </div>
<?php }

// Example: Customer fields
function customer_form_fields($customer = []) { ?>
    <div>
        <label>Name:</label>
        <input type="text" name="name" value="<?= h($customer['name'] ?? '') ?>" required>
    </div>
    <div>
        <label>Contact:</label>
        <input type="text" name="contact" value="<?= h($customer['contact'] ?? '') ?>">
    </div>
    <div>
        <label>House #:</label>
        <input type="text" name="house_no" value="<?= h($customer['house_no'] ?? '') ?>">
    </div>
    <div>
        <label>Area:</label>
        <input type="text" name="area" value="<?= h($customer['area'] ?? '') ?>">
    </div>
    <div>
        <label>City:</label>
        <input type="text" name="city" value="<?= h($customer['city'] ?? '') ?>">
    </div>
    <div>
        <label>Location:</label>
        <input type="text" name="location" value="<?= h($customer['location'] ?? '') ?>">
    </div>
<?php }

// Add similar functions for vendors, purchases, users, etc.
?>