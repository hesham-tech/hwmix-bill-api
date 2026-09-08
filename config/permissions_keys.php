<?php

/**
 * -----------------------------------------------------------------------------
 * Permission Keys Registry â€” Arabic Labels
 * -----------------------------------------------------------------------------
 * Ù‡Ø°Ø§ Ø§Ù„Ù…Ù„Ù Ù‡Ùˆ Ø§Ù„Ù…ØµØ¯Ø± Ø§Ù„ÙˆØ­ÙŠØ¯ Ø§Ù„Ø±Ø³Ù…ÙŠ Ù„ØªØ¹Ø±ÙŠÙ Ù…ÙØ§ØªÙŠØ­ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª (permission keys)
 * Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…Ø© ÙÙŠ Ø§Ù„Ø¨Ø§Ùƒ Ø¥Ù†Ø¯ ÙˆØ§Ù„ÙØ±ÙˆÙ†Øª Ø¥Ù†Ø¯ØŒ ÙˆÙŠÙØ±Ø¬Ù‰ Ø§Ù„Ø±Ø¬ÙˆØ¹ Ø¥Ù„ÙŠÙ‡ ÙÙ‚Ø· Ù„Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ø£Ø³Ù…Ø§Ø¡
 * Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø³ÙˆØ§Ø¡ ÙÙŠ Ø§Ù„ÙƒÙˆØ¯ Ø£Ùˆ Ø¹Ù†Ø¯ Ø¥Ù†Ø´Ø§Ø¡ Ø¨ÙŠØ§Ù†Ø§Øª seeder Ø£Ùˆ Ø§Ù„ØªØ¹Ø§Ù…Ù„ Ù…Ø¹Ù‡Ø§ Ù…Ù† Ø§Ù„ÙˆØ§Ø¬Ù‡Ø©.
 *
 * âœ… ÙŠÙØ³ØªØ®Ø¯Ù… Ù‡Ø°Ø§ Ø§Ù„Ù…Ù„Ù ÙÙŠ:
 * - ØªÙˆÙ„ÙŠØ¯ seeders Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª.
 * - Ø¥Ù†Ø´Ø§Ø¡ ÙˆØ§Ø¬Ù‡Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù„Ù„ÙˆØ­Ø© Ø§Ù„ØªØ­ÙƒÙ….
 * - Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª ÙÙŠ Controllers, Policies, Gates Ø¥Ù„Ø®.
 * - Ø§Ù„ØªØ±Ø¬Ù…Ø© ÙˆØ§Ù„ØªÙ…Ø«ÙŠÙ„ Ø§Ù„Ø¨ØµØ±ÙŠ Ù„Ø£Ø³Ù…Ø§Ø¡ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª.
 *
 * âœ… Ø¯Ø§Ù„Ø© Ø§Ù„Ù…Ø³Ø§Ø¹Ø¯ `perm_key('entity.action')` ØªÙØ³ØªØ®Ø¯Ù… Ù„Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ Ø§Ù„Ù…ÙØªØ§Ø­ Ø§Ù„Ø±Ø³Ù…ÙŠ.
 * âž¤ Ù…Ø«Ø§Ù„: perm_key('users.update_all') â†’ "users.update_all"
 *
 * âœ… ÙŠØ¬Ø¨ Ø£Ù† ØªØ­ØªÙˆÙŠ ÙƒÙ„ ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ù„Ù‰:
 * - key   â†’ Ø§Ù„Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ­Ø¯ Ø§Ù„Ù…Ø­ÙÙˆØ¸ ÙÙŠ Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª (Ø¨Ø§Ù„Ø¥Ù†Ø¬Ù„ÙŠØ²ÙŠØ©)
 * - label â†’ Ø§Ù„ØªØ³Ù…ÙŠØ© Ø§Ù„Ø¸Ø§Ù‡Ø±Ø© ÙÙŠ Ø§Ù„ÙˆØ§Ø¬Ù‡Ø© (Ø¨Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©)
 *
 * -----------------------------------------------------------------------------
 * Ø´Ø±Ø­ Ù…ÙØµÙ„ Ù„Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª (actions) ÙˆÙ†Ø·Ø§Ù‚Ù‡Ø§:
 * -----------------------------------------------------------------------------
 * - name: ÙŠØ´ÙŠØ± Ø¥Ù„Ù‰ Ø§Ø³Ù… Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø© Ø§Ù„ÙƒÙ„ÙŠØ© Ù„Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª ÙˆÙŠØ¹Ø¨Ø± Ø¹Ù† ÙˆØ¸ÙŠÙØªÙ‡Ø§ Ø£Ùˆ ÙŠØµÙÙ‡Ø§.
 * - page:
 * Ø§Ù„Ø³Ù…Ø§Ø­ Ø¨Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ Ø§Ù„ØµÙØ­Ø© Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ© Ø£Ùˆ Ù‚Ø§Ø¦Ù…Ø© Ø¥Ø¯Ø§Ø±Ø© ÙƒÙŠØ§Ù† Ù…Ø¹ÙŠÙ† (Ù…Ø«Ù„ 'ØµÙØ­Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†'
 * Ø£Ùˆ 'ØµÙØ­Ø© Ø§Ù„Ø´Ø±ÙƒØ§Øª'). Ù„Ø§ ØªÙ…Ù†Ø­ ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¹Ø±Ø¶ Ø§Ù„Ø³Ø¬Ù„Ø§ØªØŒ Ø¨Ù„ ÙÙ‚Ø· Ø§Ù„ÙˆØµÙˆÙ„ Ù„ÙˆØ§Ø¬Ù‡Ø© Ø§Ù„Ø¥Ø¯Ø§Ø±Ø©.
 *
 * - view_all:
 * Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø³Ø¬Ù„Ø§Øª Ù…Ù† Ø§Ù„ÙƒÙŠØ§Ù† Ø§Ù„Ù…Ø¹Ù†ÙŠ **Ø¶Ù…Ù† Ù†Ø·Ø§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©** Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù….
 * Ù„Ø§ ÙŠÙ…Ù†Ø­ ØµÙ„Ø§Ø­ÙŠØ§Øª ØªØ¹Ø¯ÙŠÙ„ Ø£Ùˆ Ø­Ø°ÙØŒ ÙˆÙŠØ±Ù‰ Ø§Ù„Ø³Ø¬Ù„Ø§Øª Ø¨ØºØ¶ Ø§Ù„Ù†Ø¸Ø± Ø¹Ù† Ù…ÙÙ†Ø´Ø¦Ù‡Ø§.
 *
 * - view_children:
 * Ø¹Ø±Ø¶ Ø§Ù„Ø³Ø¬Ù„Ø§Øª Ø§Ù„ØªÙŠ Ù‚Ø§Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ Ø¨Ø¥Ù†Ø´Ø§Ø¦Ù‡Ø§ØŒ Ø£Ùˆ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ†
 * Ø§Ù„Ø°ÙŠÙ† ÙŠØªØ¨Ø¹ÙˆÙ† Ù„Ù‡ ÙÙŠ Ø§Ù„Ù‡ÙŠÙƒÙ„ Ø§Ù„ØªÙ†Ø¸ÙŠÙ…ÙŠ (Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ† Ù„Ù‡ Ø£Ùˆ "Ø§Ù„Ø£Ø¨Ù†Ø§Ø¡"). ÙŠÙØ³ØªØ®Ø¯Ù… Ù‡Ø°Ø§
 * ÙÙŠ Ø§Ù„Ø£Ù†Ø¸Ù…Ø© Ø§Ù„Ù‡Ø±Ù…ÙŠØ© Ù„ØªÙ‚ÙŠÙŠØ¯ Ø§Ù„Ø±Ø¤ÙŠØ© Ø¶Ù…Ù† ÙØ±ÙˆØ¹ Ù…Ø¹ÙŠÙ†Ø©.
 *
 * - view_self:
 * Ø¹Ø±Ø¶ Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„Ø°ÙŠ ÙŠØ®Øµ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù†ÙØ³Ù‡ ÙÙ‚Ø·ØŒ Ù…Ø«Ù„ Ø­Ø³Ø§Ø¨Ù‡ Ø§Ù„Ø´Ø®ØµÙŠ Ø£Ùˆ ØªÙØ§ØµÙŠÙ„ Ø´Ø±ÙƒØªÙ‡
 * Ø§Ù„Ø®Ø§ØµØ© Ø¨Ù‡. ÙŠÙØ³ØªØ®Ø¯Ù… Ù‡Ø°Ø§ Ù„ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø´Ø®ØµÙŠØ© Ø¯ÙˆÙ† Ø±Ø¤ÙŠØ© Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¢Ø®Ø±ÙŠÙ†.
 *
 * - create:
 * Ø¥Ù†Ø´Ø§Ø¡ Ø³Ø¬Ù„ Ø¬Ø¯ÙŠØ¯ ÙÙŠ Ù‡Ø°Ø§ Ø§Ù„ÙƒÙŠØ§Ù† **Ø¶Ù…Ù† Ù†Ø·Ø§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©**ØŒ Ù…Ø«Ù„ Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªØ®Ø¯Ù…
 * Ø¬Ø¯ÙŠØ¯ Ø£Ùˆ Ø¥Ù†Ø´Ø§Ø¡ Ø´Ø±ÙƒØ© Ø¬Ø¯ÙŠØ¯Ø©.
 *
 * - update_all:
 * ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø³Ø¬Ù„ Ø¯Ø§Ø®Ù„ Ø§Ù„ÙƒÙŠØ§Ù† **Ø¶Ù…Ù† Ù†Ø·Ø§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©** Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù…ØŒ Ø¯ÙˆÙ† Ù‚ÙŠÙˆØ¯ Ø¹Ù„Ù‰
 * Ù…Ù† Ø£Ù†Ø´Ø£ Ø§Ù„Ø³Ø¬Ù„ Ø£Ùˆ Ù…Ù„ÙƒÙŠØªÙ‡.
 *
 * - update_children:
 * ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø³Ø¬Ù„Ø§Øª Ø§Ù„ØªÙŠ Ù‚Ø§Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ Ø¨Ø¥Ù†Ø´Ø§Ø¦Ù‡Ø§ØŒ Ø£Ùˆ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ†
 * Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ† Ù„Ù‡ ÙÙŠ Ø§Ù„Ù‡ÙŠÙƒÙ„ Ø§Ù„ØªÙ†Ø¸ÙŠÙ…ÙŠ (Ø§Ù„Ø£Ø¨Ù†Ø§Ø¡).
 *
 * - update_self:
 * ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„Ù…Ø±ØªØ¨Ø· Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù…Ø¨Ø§Ø´Ø±Ø© ÙÙ‚Ø· (Ù…Ø«Ù„ ØªØ¹Ø¯ÙŠÙ„ Ù…Ù„ÙÙ‡ Ø§Ù„Ø´Ø®ØµÙŠ Ø£Ùˆ Ø¨ÙŠØ§Ù†Ø§Øª Ø´Ø±ÙƒØªÙ‡
 * Ø§Ù„Ø®Ø§ØµØ© Ø¨Ù‡).
 *
 * - delete_all:
 * Ø­Ø°Ù Ø£ÙŠ Ø³Ø¬Ù„ Ù…Ù† Ø§Ù„ÙƒÙŠØ§Ù† **Ø¶Ù…Ù† Ù†Ø·Ø§Ù‚ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©** Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù…ØŒ Ø¨ØºØ¶ Ø§Ù„Ù†Ø¸Ø± Ø¹Ù† Ø§Ù„Ù…Ù„ÙƒÙŠØ©.
 *
 * - delete_children:
 * Ø­Ø°Ù Ø§Ù„Ø³Ø¬Ù„Ø§Øª Ø§Ù„ØªÙŠ Ù‚Ø§Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ Ø¨Ø¥Ù†Ø´Ø§Ø¦Ù‡Ø§ØŒ Ø£Ùˆ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ†
 * Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ† Ù„Ù‡ ÙÙŠ Ø§Ù„Ù‡ÙŠÙƒÙ„ Ø§Ù„ØªÙ†Ø¸ÙŠÙ…ÙŠ (Ø§Ù„Ø£Ø¨Ù†Ø§Ø¡).
 *
 * - delete_self:
 * Ø­Ø°Ù Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„Ø®Ø§Øµ Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ù†ÙØ³Ù‡ ÙÙ‚Ø· (Ø¹Ù„Ù‰ Ø³Ø¨ÙŠÙ„ Ø§Ù„Ù…Ø«Ø§Ù„ØŒ ØªØ¹Ø·ÙŠÙ„ Ø­Ø³Ø§Ø¨Ù‡ Ø§Ù„Ø´Ø®ØµÙŠ).
 *
 * â—¾ Ø§Ù„ÙƒÙŠØ§Ù†Ø§Øª (entities): Ù…Ø«Ù„ users, companies, warehouses â€¦ Ø¥Ù„Ø®.
 * â—¾ ÙƒÙ„ ÙƒÙŠØ§Ù† ÙŠØ­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ù…Ø¬Ù…ÙˆØ¹Ø© Ù…Ù† Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø­Ø³Ø¨ Ù†ÙˆØ¹ Ø§Ù„ØªØ¹Ø§Ù…Ù„ Ù…Ø¹Ù‡.
 * -----------------------------------------------------------------------------
 */
return [
    // => ADMIN
    'admin' => [
        'name' => ['key' => 'admin', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø¯ÙŠØ±ÙŠÙ†'],
        'page' => ['key' => 'admin.page', 'label' => 'Ø§Ù„ØµÙØ­Ø© Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ©'],
        'super' => ['key' => 'admin.super', 'label' => ' ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ù…Ø¯ÙŠØ± Ø§Ù„Ø¹Ø§Ù…'],
        'company' => ['key' => 'admin.company', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ© Ø§Ø¯Ø§Ø±Ø© Ø§Ù„Ø´Ø±ÙƒØ©'],
    ],
    // => COMPANIES
    'companies' => [
        'name' => ['key' => 'companies', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø´Ø±ÙƒØ§Øª'],
        'change_active_company' => ['key' => 'companies.change_active_company', 'label' => 'ØªØºÙŠÙŠØ± Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ù†Ø´Ø·Ø©'],
        'page' => ['key' => 'companies.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ø´Ø±ÙƒØ§Øª'],

        'view_all' => ['key' => 'companies.view_all', 'label' => 'Ø¹Ø±Ø¶ ÙƒÙ„ Ø§Ù„Ø´Ø±ÙƒØ§Øª'],
        'view_children' => ['key' => 'companies.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'view_self' => ['key' => 'companies.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'companies.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø´Ø±ÙƒØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'companies.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£Ù‰ Ø´Ø±ÙƒØ©'],
        'update_children' => ['key' => 'companies.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'update_self' => ['key' => 'companies.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'companies.delete_all', 'label' => 'Ø­Ø°Ù Ø£Ù‰ Ø´Ø±ÙƒØ©'],
        'delete_children' => ['key' => 'companies.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø´Ø±ÙƒØ§Øª Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'delete_self' => ['key' => 'companies.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø´Ø±ÙƒØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©'],
    ],

    // => BRANCHES
    'branches' => [
        'name' => ['key' => 'branches', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ÙØ±ÙˆØ¹'],
        'page' => ['key' => 'branches.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„ÙØ±ÙˆØ¹'],

        'view_all' => ['key' => 'branches.view_all', 'label' => 'Ø¹Ø±Ø¶ ÙƒÙ„ Ø§Ù„ÙØ±ÙˆØ¹'],
        'view_children' => ['key' => 'branches.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'view_self' => ['key' => 'branches.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù†Ø´Ø·'],
        
        'create' => ['key' => 'branches.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ ÙØ±Ø¹'],
        
        'update_all' => ['key' => 'branches.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ÙØ±Ø¹'],
        'update_children' => ['key' => 'branches.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'update_self' => ['key' => 'branches.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù†Ø´Ø·'],
        
        'delete_all' => ['key' => 'branches.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ÙØ±Ø¹'],
        'delete_children' => ['key' => 'branches.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙØ±ÙˆØ¹ Ø§Ù„ØªØ§Ø¨Ø¹Ø©'],
        'delete_self' => ['key' => 'branches.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù†Ø´Ø·'],
    ],
    // => USERS
    'users' => [
        'name' => ['key' => 'users', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†'],
        'page' => ['key' => 'users.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'users.view_all', 'label' => 'Ø¹Ø±Ø¶ ÙƒÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†'],
        'view_children' => ['key' => 'users.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'view_self' => ['key' => 'users.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø­Ø³Ø§Ø¨ Ø§Ù„Ø´Ø®ØµÙ‰'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'users.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'users.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£Ù‰ Ù…Ø³ØªØ®Ø¯Ù…'],
        'update_children' => ['key' => 'users.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'update_self' => ['key' => 'users.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø­Ø³Ø§Ø¨Ù‡'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'users.delete_all', 'label' => 'Ø­Ø°Ù Ø£Ù‰ Ù…Ø³ØªØ®Ø¯Ù…'],
        'delete_children' => ['key' => 'users.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'delete_self' => ['key' => 'users.delete_self', 'label' => 'Ø­Ø°Ù Ø­Ø³Ø§Ø¨Ù‡'],
    ],
    // => PERSONAL ACCESS TOKENS
    'personal_access_tokens' => [
        'name' => ['key' => 'personal_access_tokens', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        'page' => ['key' => 'personal_access_tokens.page', 'label' => 'ØµÙØ­Ø© Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'personal_access_tokens.view_all', 'label' => 'Ø¹Ø±Ø¶ ÙƒÙ„ Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„'],
        'view_children' => ['key' => 'personal_access_tokens.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'personal_access_tokens.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'personal_access_tokens.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø±Ù…Ø² ÙˆØµÙˆÙ„'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'personal_access_tokens.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø±Ù…Ø² ÙˆØµÙˆÙ„'],
        'update_children' => ['key' => 'personal_access_tokens.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'personal_access_tokens.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'personal_access_tokens.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø±Ù…Ø² ÙˆØµÙˆÙ„'],
        'delete_children' => ['key' => 'personal_access_tokens.delete_children', 'label' => 'Ø­Ø°Ù Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'personal_access_tokens.delete_self', 'label' => 'Ø­Ø°Ù Ø±Ù…ÙˆØ² Ø§Ù„ÙˆØµÙˆÙ„ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => TRANSLATIONS
    'translations' => [
        'name' => ['key' => 'translations', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª'],
        'page' => ['key' => 'translations.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'translations.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª'],
        'view_children' => ['key' => 'translations.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'translations.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'translations.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ ØªØ±Ø¬Ù…Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'translations.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ØªØ±Ø¬Ù…Ø©'],
        'update_children' => ['key' => 'translations.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'translations.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'translations.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ØªØ±Ø¬Ù…Ø©'],
        'delete_children' => ['key' => 'translations.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'translations.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„ØªØ±Ø¬Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => TRANSACTIONS
    'transactions' => [
        'name' => ['key' => 'transactions', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª'],
        'page' => ['key' => 'transactions.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'transactions.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª'],
        'view_children' => ['key' => 'transactions.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'transactions.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'transactions.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø¹Ø§Ù…Ù„Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'transactions.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…Ø¹Ø§Ù…Ù„Ø©'],
        'update_children' => ['key' => 'transactions.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'transactions.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'transactions.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…Ø¹Ø§Ù…Ù„Ø©'],
        'delete_children' => ['key' => 'transactions.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'transactions.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => ACTIVITY LOGS
    'activity_logs' => [
        'name' => ['key' => 'activity_logs', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø©'],
        'page' => ['key' => 'activity_logs.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'activity_logs.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø©'],
        'view_children' => ['key' => 'activity_logs.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'activity_logs.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],

        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'activity_logs.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø³Ø¬Ù„ Ù†Ø´Ø§Ø·'],
        'delete_children' => ['key' => 'activity_logs.delete_children', 'label' => 'Ø­Ø°Ù Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'activity_logs.delete_self', 'label' => 'Ø­Ø°Ù Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ù†Ø´Ø·Ø© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => CASH BOX TYPES
    'cash_box_types' => [
        'name' => ['key' => 'cash_box_types', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        'page' => ['key' => 'cash_box_types.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'cash_box_types.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        'view_children' => ['key' => 'cash_box_types.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'cash_box_types.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'cash_box_types.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù†ÙˆØ¹ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ© Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'cash_box_types.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù†ÙˆØ¹ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ©'],
        'update_children' => ['key' => 'cash_box_types.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'cash_box_types.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'cash_box_types.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù†ÙˆØ¹ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ©'],
        'delete_children' => ['key' => 'cash_box_types.delete_children', 'label' => 'Ø­Ø°Ù Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'cash_box_types.delete_self', 'label' => 'Ø­Ø°Ù Ø£Ù†ÙˆØ§Ø¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => CASH BOXES
    'cash_boxes' => [
        'name' => ['key' => 'cash_boxes', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        'page' => ['key' => 'cash_boxes.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'cash_boxes.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ©'],
        'view_children' => ['key' => 'cash_boxes.view_children', 'label' => 'Ø¹Ø±Ø¶ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'cash_boxes.view_self', 'label' => 'Ø¹Ø±Ø¶ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'cash_boxes.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ© Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'cash_boxes.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ©'],
        'update_children' => ['key' => 'cash_boxes.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'cash_boxes.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'cash_boxes.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ØµÙ†Ø¯ÙˆÙ‚ Ù†Ù‚Ø¯ÙŠØ©'],
        'delete_children' => ['key' => 'cash_boxes.delete_children', 'label' => 'Ø­Ø°Ù ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'cash_boxes.delete_self', 'label' => 'Ø­Ø°Ù ØµÙ†Ø§Ø¯ÙŠÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => IMAGES
    'images' => [
        'name' => ['key' => 'images', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ØµÙˆØ±'],
        'page' => ['key' => 'images.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„ØµÙˆØ±'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'images.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ØµÙˆØ±'],
        'view_children' => ['key' => 'images.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ØµÙˆØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'images.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ØµÙˆØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'images.create', 'label' => 'Ø¥Ø¶Ø§ÙØ© ØµÙˆØ±Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'images.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ØµÙˆØ±Ø©'],
        'update_children' => ['key' => 'images.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØµÙˆØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'images.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØµÙˆØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'images.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ØµÙˆØ±Ø©'],
        'delete_children' => ['key' => 'images.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ØµÙˆØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'images.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„ØµÙˆØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => WAREHOUSES
    'warehouses' => [
        'name' => ['key' => 'warehouses', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª'],
        'page' => ['key' => 'warehouses.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'warehouses.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª'],
        'view_children' => ['key' => 'warehouses.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'warehouses.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'warehouses.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø³ØªÙˆØ¯Ø¹ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'warehouses.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…Ø³ØªÙˆØ¯Ø¹'],
        'update_children' => ['key' => 'warehouses.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'warehouses.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'warehouses.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…Ø³ØªÙˆØ¯Ø¹'],
        'delete_children' => ['key' => 'warehouses.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'warehouses.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø³ØªÙˆØ¯Ø¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => CATEGORIES
    'categories' => [
        'name' => ['key' => 'categories', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ÙØ¦Ø§Øª'],
        'page' => ['key' => 'categories.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„ÙØ¦Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'categories.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ÙØ¦Ø§Øª'],
        'view_children' => ['key' => 'categories.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'categories.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'categories.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ ÙØ¦Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'categories.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ÙØ¦Ø©'],
        'update_children' => ['key' => 'categories.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'categories.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'categories.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ÙØ¦Ø©'],
        'delete_children' => ['key' => 'categories.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'categories.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙØ¦Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'merge' => ['key' => 'categories.merge', 'label' => 'Ø¯Ù…Ø¬ Ø§Ù„ÙØ¦Ø§Øª'],
        'globalize' => ['key' => 'categories.globalize', 'label' => 'ØªØ­ÙˆÙŠÙ„ Ø§Ù„ÙØ¦Ø© Ù„Ù†Ø¸Ø§Ù… Ø¹Ø§Ù„Ù…ÙŠ'],
    ],
    // => BRANDS
    'brands' => [
        'name' => ['key' => 'brands', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©'],
        'page' => ['key' => 'brands.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'brands.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©'],
        'view_children' => ['key' => 'brands.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'brands.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'brands.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¹Ù„Ø§Ù…Ø© ØªØ¬Ø§Ø±ÙŠØ© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'brands.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¹Ù„Ø§Ù…Ø© ØªØ¬Ø§Ø±ÙŠØ©'],
        'update_children' => ['key' => 'brands.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'brands.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'brands.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¹Ù„Ø§Ù…Ø© ØªØ¬Ø§Ø±ÙŠØ©'],
        'delete_children' => ['key' => 'brands.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'brands.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'merge' => ['key' => 'brands.merge', 'label' => 'Ø¯Ù…Ø¬ Ø§Ù„Ø¹Ù„Ø§Ù…Ø§Øª Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©'],
        'globalize' => ['key' => 'brands.globalize', 'label' => 'ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ø¹Ù„Ø§Ù…Ø© Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ© Ù„Ù†Ø¸Ø§Ù… Ø¹Ø§Ù„Ù…ÙŠ'],
    ],
    // => ATTRIBUTES
    'attributes' => [
        'name' => ['key' => 'attributes', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø³Ù…Ø§Øª'],
        'page' => ['key' => 'attributes.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø³Ù…Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'attributes.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø³Ù…Ø§Øª'],
        'view_children' => ['key' => 'attributes.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'attributes.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'attributes.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø³Ù…Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'attributes.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø³Ù…Ø©'],
        'update_children' => ['key' => 'attributes.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'attributes.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'attributes.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø³Ù…Ø©'],
        'delete_children' => ['key' => 'attributes.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'attributes.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => ATTRIBUTE VALUES
    'attribute_values' => [
        'name' => ['key' => 'attribute_values', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª'],
        'page' => ['key' => 'attribute_values.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'attribute_values.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª'],
        'view_children' => ['key' => 'attribute_values.view_children', 'label' => 'Ø¹Ø±Ø¶ Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'attribute_values.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'attribute_values.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù‚ÙŠÙ…Ø© Ø³Ù…Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'attribute_values.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù‚ÙŠÙ…Ø© Ø³Ù…Ø©'],
        'update_children' => ['key' => 'attribute_values.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'attribute_values.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'attribute_values.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù‚ÙŠÙ…Ø© Ø³Ù…Ø©'],
        'delete_children' => ['key' => 'attribute_values.delete_children', 'label' => 'Ø­Ø°Ù Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'attribute_values.delete_self', 'label' => 'Ø­Ø°Ù Ù‚ÙŠÙ… Ø§Ù„Ø³Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => PRODUCTS
    'products' => [
        'name' => ['key' => 'products', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'page' => ['key' => 'products.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'products.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'view_children' => ['key' => 'products.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'products.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'products.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù†ØªØ¬ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'products.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…Ù†ØªØ¬'],
        'update_children' => ['key' => 'products.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'products.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'products.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…Ù†ØªØ¬'],
        'delete_children' => ['key' => 'products.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'products.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'view_wholesale_price' => ['key' => 'products.view_wholesale_price', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¹Ø± Ø§Ù„Ø¬Ù…Ù„Ø©'],
        'view_purchase_price' => ['key' => 'products.view_purchase_price', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¹Ø± Ø§Ù„Ø´Ø±Ø§Ø¡'],
        'print_labels' => ['key' => 'products.print_labels', 'label' => 'Ø·Ø¨Ø§Ø¹Ø© Ø§Ù„Ù…Ù„ØµÙ‚Ø§Øª ÙˆØ§Ù„Ø¨Ø§Ø±ÙƒÙˆØ¯'],
        'import' => ['key' => 'products.import', 'label' => 'Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'export' => ['key' => 'products.export', 'label' => 'ØªØµØ¯ÙŠØ± Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
    ],
    // => PRODUCT VARIANTS
    'product_variants' => [
        'name' => ['key' => 'product_variants', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'page' => ['key' => 'product_variants.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'product_variants.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'view_children' => ['key' => 'product_variants.view_children', 'label' => 'Ø¹Ø±Ø¶ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'product_variants.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'product_variants.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'product_variants.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬'],
        'update_children' => ['key' => 'product_variants.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'product_variants.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'product_variants.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬'],
        'delete_children' => ['key' => 'product_variants.delete_children', 'label' => 'Ø­Ø°Ù Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'product_variants.delete_self', 'label' => 'Ø­Ø°Ù Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => PRODUCT VARIANT ATTRIBUTES
    'product_variant_attributes' => [
        'name' => ['key' => 'product_variant_attributes', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'page' => ['key' => 'product_variant_attributes.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'product_variant_attributes.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª'],
        'view_children' => ['key' => 'product_variant_attributes.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'product_variant_attributes.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'product_variant_attributes.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø³Ù…Ø© Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬ Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'product_variant_attributes.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø³Ù…Ø© Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬'],
        'update_children' => ['key' => 'product_variant_attributes.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'product_variant_attributes.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'product_variant_attributes.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø³Ù…Ø© Ù…ØªØºÙŠØ± Ù…Ù†ØªØ¬'],
        'delete_children' => ['key' => 'product_variant_attributes.delete_children', 'label' => 'Ø­Ø°Ù Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'product_variant_attributes.delete_self', 'label' => 'Ø­Ø°Ù Ø³Ù…Ø§Øª Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => STOCKS
    'stocks' => [
        'name' => ['key' => 'stocks', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø®Ø²ÙˆÙ†'],
        'page' => ['key' => 'stocks.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…Ø®Ø²ÙˆÙ†'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'stocks.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ†'],
        'view_children' => ['key' => 'stocks.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'stocks.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'stocks.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø³Ø¬Ù„ Ù…Ø®Ø²ÙˆÙ† Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'stocks.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø³Ø¬Ù„ Ù…Ø®Ø²ÙˆÙ†'],
        'update_children' => ['key' => 'stocks.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'stocks.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'stocks.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø³Ø¬Ù„ Ù…Ø®Ø²ÙˆÙ†'],
        'delete_children' => ['key' => 'stocks.delete_children', 'label' => 'Ø­Ø°Ù Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'stocks.delete_self', 'label' => 'Ø­Ø°Ù Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'manual_adjustment' => ['key' => 'stocks.manual_adjustment', 'label' => 'Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙŠØ¯ÙˆÙŠ Ù„Ù„Ù…Ø®Ø²ÙˆÙ†'],
    ],
    // => INVOICES
    'invoices' => [
        'name' => ['key' => 'invoices', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ÙÙˆØ§ØªÙŠØ±'],
        'page' => ['key' => 'invoices.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„ÙÙˆØ§ØªÙŠØ±'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'invoices.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ÙÙˆØ§ØªÙŠØ±'],
        'view_children' => ['key' => 'invoices.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'invoices.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'invoices.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ ÙØ§ØªÙˆØ±Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'invoices.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ÙØ§ØªÙˆØ±Ø©'],
        'update_children' => ['key' => 'invoices.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'invoices.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'invoices.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ÙØ§ØªÙˆØ±Ø©'],
        'delete_children' => ['key' => 'invoices.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'invoices.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'print' => ['key' => 'invoices.print', 'label' => 'Ø·Ø¨Ø§Ø¹Ø© Ø§Ù„ÙÙˆØ§ØªÙŠØ±'],
    ],
    // => INSTALLMENT PLANS
    'installment_plans' => [
        'name' => ['key' => 'installment_plans', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'page' => ['key' => 'installment_plans.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'installment_plans.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'view_children' => ['key' => 'installment_plans.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'installment_plans.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'installment_plans.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø®Ø·Ø© Ø£Ù‚Ø³Ø§Ø· Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'installment_plans.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø®Ø·Ø© Ø£Ù‚Ø³Ø§Ø·'],
        'update_children' => ['key' => 'installment_plans.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'installment_plans.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'installment_plans.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø®Ø·Ø© Ø£Ù‚Ø³Ø§Ø·'],
        'delete_children' => ['key' => 'installment_plans.delete_children', 'label' => 'Ø­Ø°Ù Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'installment_plans.delete_self', 'label' => 'Ø­Ø°Ù Ø®Ø·Ø· Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => INSTALLMENTS
    'installments' => [
        'name' => ['key' => 'installments', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'page' => ['key' => 'installments.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'installments.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'view_children' => ['key' => 'installments.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'installments.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'installments.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù‚Ø³Ø· Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'installments.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù‚Ø³Ø·'],
        'update_children' => ['key' => 'installments.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'installments.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'installments.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù‚Ø³Ø·'],
        'delete_children' => ['key' => 'installments.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'installments.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => INSTALLMENT PAYMENTS
    'installment_payments' => [
        'name' => ['key' => 'installment_payments', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'page' => ['key' => 'installment_payments.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'installment_payments.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø·'],
        'view_children' => ['key' => 'installment_payments.view_children', 'label' => 'Ø¹Ø±Ø¶ Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'installment_payments.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'installment_payments.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¯ÙØ¹Ø© Ù‚Ø³Ø· Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'installment_payments.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¯ÙØ¹Ø© Ù‚Ø³Ø·'],
        'update_children' => ['key' => 'installment_payments.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'installment_payments.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'installment_payments.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¯ÙØ¹Ø© Ù‚Ø³Ø·'],
        'delete_children' => ['key' => 'installment_payments.delete_children', 'label' => 'Ø­Ø°Ù Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'installment_payments.delete_self', 'label' => 'Ø­Ø°Ù Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø£Ù‚Ø³Ø§Ø· Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => INVOICE ITEMS
    'invoice_items' => [
        'name' => ['key' => 'invoice_items', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø©'],
        'page' => ['key' => 'invoice_items.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'invoice_items.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø©'],
        'view_children' => ['key' => 'invoice_items.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'invoice_items.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'invoice_items.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¹Ù†ØµØ± ÙØ§ØªÙˆØ±Ø© Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'invoice_items.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¹Ù†ØµØ± ÙØ§ØªÙˆØ±Ø©'],
        'update_children' => ['key' => 'invoice_items.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'invoice_items.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'invoice_items.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¹Ù†ØµØ± ÙØ§ØªÙˆØ±Ø©'],
        'delete_children' => ['key' => 'invoice_items.delete_children', 'label' => 'Ø­Ø°Ù Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'invoice_items.delete_self', 'label' => 'Ø­Ø°Ù Ø¹Ù†Ø§ØµØ± Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => PAYMENTS
    'payments' => [
        'name' => ['key' => 'payments', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª'],
        'page' => ['key' => 'payments.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'payments.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª'],
        'view_children' => ['key' => 'payments.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'payments.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'payments.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¯ÙØ¹Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'payments.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¯ÙØ¹Ø©'],
        'update_children' => ['key' => 'payments.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'payments.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'payments.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¯ÙØ¹Ø©'],
        'delete_children' => ['key' => 'payments.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'payments.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => PAYMENT METHODS
    'payment_methods' => [
        'name' => ['key' => 'payment_methods', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹'],
        'page' => ['key' => 'payment_methods.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'payment_methods.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹'],
        'view_children' => ['key' => 'payment_methods.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'payment_methods.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'payment_methods.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø·Ø±ÙŠÙ‚Ø© Ø¯ÙØ¹ Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'payment_methods.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø·Ø±ÙŠÙ‚Ø© Ø¯ÙØ¹'],
        'update_children' => ['key' => 'payment_methods.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'payment_methods.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'payment_methods.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø·Ø±ÙŠÙ‚Ø© Ø¯ÙØ¹'],
        'delete_children' => ['key' => 'payment_methods.delete_children', 'label' => 'Ø­Ø°Ù Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'payment_methods.delete_self', 'label' => 'Ø­Ø°Ù Ø·Ø±Ù‚ Ø§Ù„Ø¯ÙØ¹ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => REVENUES
    'revenues' => [
        'name' => ['key' => 'revenues', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª'],
        'page' => ['key' => 'revenues.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'revenues.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª'],
        'view_children' => ['key' => 'revenues.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'revenues.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'revenues.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø³Ø¬Ù„ Ø¥ÙŠØ±Ø§Ø¯ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'revenues.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¥ÙŠØ±Ø§Ø¯'],
        'update_children' => ['key' => 'revenues.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'revenues.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'revenues.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¥ÙŠØ±Ø§Ø¯'],
        'delete_children' => ['key' => 'revenues.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'revenues.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => PROFITS
    'profits' => [
        'name' => ['key' => 'profits', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø£Ø±Ø¨Ø§Ø­'],
        'page' => ['key' => 'profits.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø£Ø±Ø¨Ø§Ø­'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'profits.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­'],
        'view_children' => ['key' => 'profits.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'profits.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'profits.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø³Ø¬Ù„ Ø±Ø¨Ø­ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'profits.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø±Ø¨Ø­'],
        'update_children' => ['key' => 'profits.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'profits.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'profits.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø±Ø¨Ø­'],
        'delete_children' => ['key' => 'profits.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'profits.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => SERVICES
    'services' => [
        'name' => ['key' => 'services', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø®Ø¯Ù…Ø§Øª'],
        'page' => ['key' => 'services.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø®Ø¯Ù…Ø§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'services.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø®Ø¯Ù…Ø§Øª'],
        'view_children' => ['key' => 'services.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'services.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'services.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø®Ø¯Ù…Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'services.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø®Ø¯Ù…Ø©'],
        'update_children' => ['key' => 'services.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'services.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'services.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø®Ø¯Ù…Ø©'],
        'delete_children' => ['key' => 'services.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'services.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => SUBSCRIPTIONS
    'subscriptions' => [
        'name' => ['key' => 'subscriptions', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª'],
        'page' => ['key' => 'subscriptions.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'subscriptions.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª'],
        'view_children' => ['key' => 'subscriptions.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'subscriptions.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'subscriptions.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø§Ø´ØªØ±Ø§Ùƒ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'subscriptions.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø§Ø´ØªØ±Ø§Ùƒ'],
        'update_children' => ['key' => 'subscriptions.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'subscriptions.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'subscriptions.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø§Ø´ØªØ±Ø§Ùƒ'],
        'delete_children' => ['key' => 'subscriptions.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'subscriptions.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø§Ø´ØªØ±Ø§ÙƒØ§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => ROLES
    'roles' => [
        'name' => ['key' => 'roles', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø£Ø¯ÙˆØ§Ø±'],
        'page' => ['key' => 'roles.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ø£Ø¯ÙˆØ§Ø±'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'roles.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ø¯ÙˆØ§Ø±'],
        'view_children' => ['key' => 'roles.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'view_self' => ['key' => 'roles.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'roles.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¯ÙˆØ± Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'roles.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø¯ÙˆØ±'],
        'update_children' => ['key' => 'roles.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'update_self' => ['key' => 'roles.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'roles.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø¯ÙˆØ±'],
        'delete_children' => ['key' => 'roles.delete_children', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªÙŠ Ø£Ù†Ø´Ø£Ù‡Ø§ Ø§Ù„ØªØ§Ø¨Ø¹ÙˆÙ†'],
        'delete_self' => ['key' => 'roles.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
    ],
    // => EXPENSES
    'expenses' => [
        'name' => ['key' => 'expenses', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ'],
        'page' => ['key' => 'expenses.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ù„Ù‰ ØµÙØ­Ø© Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¹Ø±Ø¶ (View)
        'view_all' => ['key' => 'expenses.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ'],
        'view_children' => ['key' => 'expenses.view_children', 'label' => 'Ø¹Ø±Ø¶ Ù…ØµØ§Ø±ÙŠÙ Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'view_self' => ['key' => 'expenses.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡ (Create)
        'create' => ['key' => 'expenses.create', 'label' => 'ØªØ³Ø¬ÙŠÙ„ Ù…ØµØ±ÙˆÙ Ø¬Ø¯ÙŠØ¯'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ (Update)
        'update_all' => ['key' => 'expenses.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…ØµØ±ÙˆÙ'],
        'update_children' => ['key' => 'expenses.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…ØµØ§Ø±ÙŠÙ Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'update_self' => ['key' => 'expenses.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…ØµØ±ÙˆÙ Ø§Ù„Ø´Ø®ØµÙŠ'],
        // ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ø­Ø°Ù (Delete)
        'delete_all' => ['key' => 'expenses.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…ØµØ±ÙˆÙ'],
        'delete_children' => ['key' => 'expenses.delete_children', 'label' => 'Ø­Ø°Ù Ù…ØµØ§Ø±ÙŠÙ Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'delete_self' => ['key' => 'expenses.delete_self', 'label' => 'Ø­Ø°Ù Ø§Ù„Ù…ØµØ±ÙˆÙ Ø§Ù„Ø´Ø®ØµÙŠ'],
    ],
    // => EXPENSE CATEGORIES
    'expense_categories' => [
        'name' => ['key' => 'expense_categories', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© ØªØµÙ†ÙŠÙØ§Øª Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ'],
        'page' => ['key' => 'expense_categories.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ù„ØµÙØ­Ø© ØªØµÙ†ÙŠÙØ§Øª Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ'],
        'view_all' => ['key' => 'expense_categories.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ØªØµÙ†ÙŠÙØ§Øª'],
        'create' => ['key' => 'expense_categories.create', 'label' => 'Ø¥Ø¶Ø§ÙØ© ØªØµÙ†ÙŠÙ Ø¬Ø¯ÙŠØ¯'],
        'update_all' => ['key' => 'expense_categories.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ ØªØµÙ†ÙŠÙ'],
        'delete_all' => ['key' => 'expense_categories.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ ØªØµÙ†ÙŠÙ'],
    ],
    // => FINANCIAL LEDGER
    'financial_ledger' => [
        'name' => ['key' => 'financial_ledger', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¯ÙØªØ± Ø§Ù„Ø£Ø³ØªØ§Ø° Ø§Ù„Ø¹Ø§Ù…'],
        'page' => ['key' => 'financial_ledger.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ù„Ø¯ÙØªØ± Ø§Ù„Ø£Ø³ØªØ§Ø°'],
        'view_all' => ['key' => 'financial_ledger.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù‚ÙŠÙˆØ¯ Ø§Ù„Ù…Ø­Ø§Ø³Ø¨ÙŠØ©'],
        'view_self' => ['key' => 'financial_ledger.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù‚ÙŠÙˆØ¯ Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…'],
        'export' => ['key' => 'financial_ledger.export', 'label' => 'ØªØµØ¯ÙŠØ± Ø³Ø¬Ù„Ø§Øª Ø§Ù„Ø£Ø³ØªØ§Ø°'],
    ],
    // => REPORTS
    'reports' => [
        'name' => ['key' => 'reports', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±'],
        'page' => ['key' => 'reports.page', 'label' => 'Ø§Ù„ÙˆØµÙˆÙ„ Ù„ØµÙØ­Ø© Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±'],
        'view_all' => ['key' => 'reports.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±'],
        'sales' => ['key' => 'reports.sales', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ù…Ø¨ÙŠØ¹Ø§Øª'],
        'stock' => ['key' => 'reports.stock', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ù…Ø®Ø²ÙˆÙ†'],
        'profit' => ['key' => 'reports.profit', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ø£Ø±Ø¨Ø§Ø­ ÙˆØ§Ù„Ø®Ø³Ø§Ø¦Ø±'],
        'expenses' => ['key' => 'reports.expenses', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ù…ØµØ±ÙˆÙØ§Øª Ø§Ù„ØªÙØµÙŠÙ„ÙŠ'],
        'cash_flow' => ['key' => 'reports.cash_flow', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„ØªØ¯ÙÙ‚ Ø§Ù„Ù†Ù‚Ø¯ÙŠ'],
        'tax' => ['key' => 'reports.tax', 'label' => 'Ø¹Ø±Ø¶ ØªÙ‚Ø±ÙŠØ± Ø§Ù„Ø¶Ø±Ø§Ø¦Ø¨'],
        'export' => ['key' => 'reports.export', 'label' => 'ØªØµØ¯ÙŠØ± Ø§Ù„ØªÙ‚Ø§Ø±ÙŠØ±'],
    ],
    // => INVOICE TYPES
    'invoice_types' => [
        'name' => ['key' => 'invoice_types', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª'],
        'page' => ['key' => 'invoice_types.page', 'label' => 'ØµÙØ­Ø© Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª'],
        'view_all' => ['key' => 'invoice_types.view_all', 'label' => 'Ø¹Ø±Ø¶ ÙƒÙ„ Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª'],
        'view_children' => ['key' => 'invoice_types.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ù†ÙˆØ§Ø¹ Ù„Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'view_self' => ['key' => 'invoice_types.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø®Ø§ØµØ©'],
        'update_all' => ['key' => 'invoice_types.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù†ÙˆØ¹ (ØªÙØ¹ÙŠÙ„/ØªØ¹Ø·ÙŠÙ„)'],
    ],
    // => PLANS
    'plans' => [
        'name' => ['key' => 'plans', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø®Ø·Ø· Ø§Ù„Ø£Ø³Ø¹Ø§Ø±'],
        'page' => ['key' => 'plans.page', 'label' => 'ØµÙØ­Ø© Ø®Ø·Ø· Ø§Ù„Ø£Ø³Ø¹Ø§Ø±'],
        'view_all' => ['key' => 'plans.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø®Ø·Ø·'],
        'view_children' => ['key' => 'plans.view_children', 'label' => 'Ø¹Ø±Ø¶ Ø®Ø·Ø· Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'view_self' => ['key' => 'plans.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø®Ø·Ø·ÙŠ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        'create' => ['key' => 'plans.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø®Ø·Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        'update_all' => ['key' => 'plans.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ø®Ø·Ø©'],
        'update_children' => ['key' => 'plans.update_children', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø®Ø·Ø· Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'update_self' => ['key' => 'plans.update_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø®Ø·ØªÙŠ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        'delete_all' => ['key' => 'plans.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ø®Ø·Ø©'],
        'delete_children' => ['key' => 'plans.delete_children', 'label' => 'Ø­Ø°Ù Ø®Ø·Ø· Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'delete_self' => ['key' => 'plans.delete_self', 'label' => 'Ø­Ø°Ù Ø®Ø·ØªÙŠ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
    ],
    // => TASKS
    'tasks' => [
        'name' => ['key' => 'tasks', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ù‡Ø§Ù…'],
        'page' => ['key' => 'tasks.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ù…Ù‡Ø§Ù…'],
        'view_all' => ['key' => 'tasks.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ù‡Ø§Ù…'],
        'view_children' => ['key' => 'tasks.view_children', 'label' => 'Ø¹Ø±Ø¶ Ù…Ù‡Ø§Ù… Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ†'],
        'view_self' => ['key' => 'tasks.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù…Ù‡Ø§Ù…ÙŠ Ø§Ù„Ø´Ø®ØµÙŠØ©'],
        'create' => ['key' => 'tasks.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù‡Ù…Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        'update_all' => ['key' => 'tasks.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£ÙŠ Ù…Ù‡Ù…Ø©'],
        'delete_all' => ['key' => 'tasks.delete_all', 'label' => 'Ø­Ø°Ù Ø£ÙŠ Ù…Ù‡Ù…Ø©'],
    ],
    // => ERROR REPORTS
    'error_reports' => [
        'name' => ['key' => 'error_reports', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª ØªÙ‚Ø§Ø±ÙŠØ± Ø§Ù„Ø£Ø®Ø·Ø§Ø¡'],
        'page' => ['key' => 'error_reports.page', 'label' => 'ØµÙØ­Ø© ØªÙ‚Ø§Ø±ÙŠØ± Ø§Ù„Ø£Ø®Ø·Ø§Ø¡'],
        'view_all' => ['key' => 'error_reports.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ ØªÙ‚Ø§Ø±ÙŠØ± Ø§Ù„Ø£Ø®Ø·Ø§Ø¡'],
        'update_all' => ['key' => 'error_reports.update_all', 'label' => 'ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„ØªÙ‚Ø±ÙŠØ±'],
    ],
    // => BACKUPS
    'backups' => [
        'name' => ['key' => 'backups', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù†Ø³Ø® Ø§Ù„Ø§Ø­ØªÙŠØ§Ø·ÙŠ'],
        'page' => ['key' => 'backups.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ù†Ø³Ø® Ø§Ù„Ø§Ø­ØªÙŠØ§Ø·ÙŠ'],
        'create' => ['key' => 'backups.create', 'label' => 'ØªØ´ØºÙŠÙ„ Ù†Ø³Ø®Ø© Ø§Ø­ØªÙŠØ§Ø·ÙŠØ©'],
        'view_all' => ['key' => 'backups.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù†Ø³Ø® Ø§Ù„Ø³Ø§Ø¨Ù‚Ø©'],
    ],
    // => QUOTATIONS
    'quotations' => [
        'name' => ['key' => 'quotations', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¹Ø±ÙˆØ¶ Ø§Ù„Ø£Ø³Ø¹Ø§Ø±'],
        'page' => ['key' => 'quotations.page', 'label' => 'ØµÙØ­Ø© Ø¹Ø±ÙˆØ¶ Ø§Ù„Ø£Ø³Ø¹Ø§Ø±'],
        'view_all' => ['key' => 'quotations.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø¹Ø±ÙˆØ¶ Ø§Ù„Ø£Ø³Ø¹Ø§Ø±'],
        'create' => ['key' => 'quotations.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø¹Ø±Ø¶ Ø³Ø¹Ø±'],
    ],
    // => ORDERS
    'orders' => [
        'name' => ['key' => 'orders', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø·Ù„Ø¨Ø§Øª Ø§Ù„Ø´Ø±Ø§Ø¡/Ø§Ù„Ø¨ÙŠØ¹'],
        'page' => ['key' => 'orders.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ø·Ù„Ø¨Ø§Øª'],
        'view_all' => ['key' => 'orders.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø·Ù„Ø¨Ø§Øª'],
        'create' => ['key' => 'orders.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ø·Ù„Ø¨ Ø¬Ø¯ÙŠØ¯'],
    ],
    'balance' => [
        'name' => ['key' => 'balance', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø£Ø±ØµØ¯Ø© ÙˆØ§Ù„Ù…Ø§Ù„ÙŠØ§Øª'],
        'deposit_any' => ['key' => 'balance.deposit_any', 'label' => 'Ø¥ÙŠØ¯Ø§Ø¹ Ø±ØµÙŠØ¯ Ù„Ø£ÙŠ Ù…Ø³ØªØ®Ø¯Ù…'],
        'withdraw_any' => ['key' => 'balance.withdraw_any', 'label' => 'Ø³Ø­Ø¨ Ø±ØµÙŠØ¯ Ù…Ù† Ø£ÙŠ Ù…Ø³ØªØ®Ø¯Ù…'],
        'transfer_any' => ['key' => 'balance.transfer_any', 'label' => 'ØªØ­ÙˆÙŠÙ„ Ø±ØµÙŠØ¯ Ù…Ù† Ø£ÙŠ Ù…Ø³ØªØ®Ø¯Ù…'],
        'deposit' => ['key' => 'balance.deposit', 'label' => 'Ø¥Ø¬Ø±Ø§Ø¡ Ø¥ÙŠØ¯Ø§Ø¹ Ø±ØµÙŠØ¯ (Ø´Ø®ØµÙŠ)'],
        'withdraw' => ['key' => 'balance.withdraw', 'label' => 'Ø¥Ø¬Ø±Ø§Ø¡ Ø³Ø­Ø¨ Ø±ØµÙŠØ¯ (Ø´Ø®ØµÙŠ)'],
        'transfer' => ['key' => 'balance.transfer', 'label' => 'تحويل رصيد (شخصي)'],
        'adjust_balance' => ['key' => 'cashbox.adjust_balance', 'label' => 'مطابقة وتسوية الأرصدة'],
    ],
    // => LEGAL DOCUMENTS
    'legal_documents' => [
        'name' => ['key' => 'legal_documents', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©'],
        'page' => ['key' => 'legal_documents.page', 'label' => 'ØµÙØ­Ø© Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©'],
        'view_all' => ['key' => 'legal_documents.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø³ØªÙ†Ø¯Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©'],
        'create' => ['key' => 'legal_documents.create', 'label' => 'Ø¥Ù†Ø´Ø§Ø¡ Ù…Ø³ÙˆØ¯Ø© Ù…Ø³ØªÙ†Ø¯ Ø¬Ø¯ÙŠØ¯'],
        'update_all' => ['key' => 'legal_documents.update_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø£Ùˆ ØµÙŠØ§ØºØ© Ø¥ØµØ¯Ø§Ø± Ø¬Ø¯ÙŠØ¯'],
        'delete_all' => ['key' => 'legal_documents.delete_all', 'label' => 'Ø­Ø°Ù Ù…Ø³ÙˆØ¯Ø© Ù…Ø³ØªÙ†Ø¯ Ø£Ùˆ Ø¥ØµØ¯Ø§Ø± ØºÙŠØ± Ù…Ù†Ø´ÙˆØ±'],
    ],
    // => HWNIX CASH MODULE
    'hwnix_cash' => [
        'name' => ['key' => 'hwnix_cash', 'label' => 'ØµÙ„Ø§Ø­ÙŠØ§Øª ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³ Ø§Ù„ÙƒØ§Ù…Ù„Ø©'],
        'page' => ['key' => 'hwnix_cash.page', 'label' => 'ØµÙØ­Ø© Ø£Ø¬Ù‡Ø²Ø© ÙˆÙ…ÙˆØ¯ÙŠÙˆÙ„ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],
        'view_all' => ['key' => 'hwnix_cash.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø£Ø¬Ù‡Ø²Ø© ÙˆØ´Ø±Ø§Ø¦Ø­ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],
        'view_self' => ['key' => 'hwnix_cash.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø£Ø¬Ù‡Ø²Ø© ÙˆØ´Ø±Ø§Ø¦Ø­ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³ Ø§Ù„Ø®Ø§ØµØ©'],
        'edit_all' => ['key' => 'hwnix_cash.edit_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø¬Ù…ÙŠØ¹ Ø®Ø·ÙˆØ· ÙˆØ´Ø±Ø§Ø¦Ø­ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],
        'edit_self' => ['key' => 'hwnix_cash.edit_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø®Ø·ÙˆØ· ÙˆØ´Ø±Ø§Ø¦Ø­ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³ Ø§Ù„Ø®Ø§ØµØ©'],
        'delete_all' => ['key' => 'hwnix_cash.delete_all', 'label' => 'Ø­Ø°Ù ÙˆØ¥Ù„ØºØ§Ø¡ Ø±Ø¨Ø· Ø£Ø¬Ù‡Ø²Ø© ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],

        // Ø±Ø³Ø§Ø¦Ù„ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³
        'messages_page' => ['key' => 'hwnix_cash_messages.page', 'label' => 'ØµÙØ­Ø© Ø³Ø¬Ù„Ø§Øª Ø±Ø³Ø§Ø¦Ù„ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],
        'messages_view_all' => ['key' => 'hwnix_cash_messages.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ø±Ø³Ø§Ø¦Ù„ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],
        'messages_view_self' => ['key' => 'hwnix_cash_messages.view_self', 'label' => 'Ø¹Ø±Ø¶ Ø±Ø³Ø§Ø¦Ù„ ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³ Ø§Ù„Ø®Ø§ØµØ©'],
        'messages_create' => ['key' => 'hwnix_cash_messages.create', 'label' => 'Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ø¦Ù„ Ø¬Ø¯ÙŠØ¯Ø© Ø¹Ø¨Ø± ÙƒØ§Ø´ Ù‡ÙˆÙ†ÙƒØ³'],

        // Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠØ©
        'wallet_transactions_page' => ['key' => 'hwnix_cash_wallet_transactions.page', 'label' => 'ØµÙØ­Ø© Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠØ©'],
        'wallet_transactions_view_all' => ['key' => 'hwnix_cash_wallet_transactions.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸'],
        'wallet_transactions_view_self' => ['key' => 'hwnix_cash_wallet_transactions.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸ Ø§Ù„Ø®Ø§ØµØ©'],
        'wallet_transactions_create' => ['key' => 'hwnix_cash_wallet_transactions.create', 'label' => 'Ø¥Ø¶Ø§ÙØ© Ù…Ø¹Ø§Ù…Ù„Ø© Ù…Ø­ÙØ¸Ø© Ø¬Ø¯ÙŠØ¯Ø©'],
        'wallet_transactions_edit_all' => ['key' => 'hwnix_cash_wallet_transactions.edit_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø¬Ù…ÙŠØ¹ Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸'],
        'wallet_transactions_edit_self' => ['key' => 'hwnix_cash_wallet_transactions.edit_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ø­Ø§ÙØ¸ Ø§Ù„Ø®Ø§ØµØ©'],
        'wallet_transactions_delete_all' => ['key' => 'hwnix_cash_wallet_transactions.delete_all', 'label' => 'Ø­Ø°Ù Ù…Ø¹Ø§Ù…Ù„Ø© Ù…Ø­ÙØ¸Ø© Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠØ©'],
        'wallet_transactions_view_parsed_by' => ['key' => 'hwnix_cash_wallet_transactions.view_parsed_by', 'label' => 'Ø¹Ø±Ø¶ Ù…Ù†ÙØ° ØªØ­Ù„ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø©'],
        'wallet_transactions_view_parser_stage' => ['key' => 'hwnix_cash_wallet_transactions.view_parser_stage', 'label' => 'Ø¹Ø±Ø¶ Ù…Ø±Ø­Ù„Ø© ØªØ­Ù„ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø©'],

        // Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ù…Ø¹ØªÙ…Ø¯Ø©
        'message_sources_page' => ['key' => 'hwnix_cash_message_sources.page', 'label' => 'ØµÙØ­Ø© Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ù…Ø¹ØªÙ…Ø¯Ø©'],
        'message_sources_view_all' => ['key' => 'hwnix_cash_message_sources.view_all', 'label' => 'Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ù…Ø¹ØªÙ…Ø¯Ø©'],
        'message_sources_view_self' => ['key' => 'hwnix_cash_message_sources.view_self', 'label' => 'Ø¹Ø±Ø¶ Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ø®Ø§ØµØ©'],
        'message_sources_create' => ['key' => 'hwnix_cash_message_sources.create', 'label' => 'Ø¥Ø¶Ø§ÙØ© Ù…ØµØ¯Ø± Ø±Ø³Ø§Ø¦Ù„ Ù…Ø¹ØªÙ…Ø¯'],
        'message_sources_edit_all' => ['key' => 'hwnix_cash_message_sources.edit_all', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ø¬Ù…ÙŠØ¹ Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„'],
        'message_sources_edit_self' => ['key' => 'hwnix_cash_message_sources.edit_self', 'label' => 'ØªØ¹Ø¯ÙŠÙ„ Ù…ØµØ§Ø¯Ø± Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ø®Ø§ØµØ©'],
        'message_sources_delete_all' => ['key' => 'hwnix_cash_message_sources.delete_all', 'label' => 'Ø­Ø°Ù Ù…ØµØ¯Ø± Ø±Ø³Ø§Ø¦Ù„ Ù…Ø¹ØªÙ…Ø¯'],
    ],
    // => CUSTODIES
    'custodies' => [
        'name' => ['key' => 'custodies', 'label' => 'صلاحيات العهد'],
        'page' => ['key' => 'custodies.page', 'label' => 'الوصول لصفحة العهد'],
        'view_all' => ['key' => 'custodies.view_all', 'label' => 'عرض كل العهد'],
        'view_self' => ['key' => 'custodies.view_self', 'label' => 'عرض العهد الشخصية'],
        'create' => ['key' => 'custodies.create', 'label' => 'إصدار عهدة'],
        'refund' => ['key' => 'custodies.refund', 'label' => 'استرداد نقدي من عهدة'],
        'reverse' => ['key' => 'custodies.reverse', 'label' => 'عكس العهدة'],
    ],
    // => OWNER FUNDS
    'owner_fund_transactions' => [
        'name' => ['key' => 'owner_fund_transactions', 'label' => 'صلاحيات معاملات الشركاء'],
        'page' => ['key' => 'owner_fund_transactions.page', 'label' => 'الوصول لصفحة معاملات الشركاء'],
        'view_all' => ['key' => 'owner_fund_transactions.view_all', 'label' => 'عرض معاملات الشركاء'],
        'create' => ['key' => 'owner_fund_transactions.create', 'label' => 'إنشاء معاملة شريك'],
        'reverse' => ['key' => 'owner_fund_transactions.reverse', 'label' => 'عكس معاملة شريك'],
    ],
];





