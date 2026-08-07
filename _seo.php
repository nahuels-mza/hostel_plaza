<?php
/**
 * SEO tags — include in <head> of every page.
 * The page must define $seo[] before including this file.
 *
 * $seo keys:
 *   title       string  Page title shown in tab and search results
 *   description string  Meta description (≤160 chars)
 *   url         string  Canonical URL (absolute)
 *   image       string  Absolute URL for OG image (1200×630 ideal)
 *   type        string  og:type — 'website' (default) or 'article'
 *   schema      array   JSON-LD data (Schema.org)
 */
$_t = $seo['title']       ?? 'Hostel Plaza | Mendoza, Argentina';
$_d = $seo['description'] ?? 'Boutique hostel in the heart of Mendoza, Argentina. Private & shared rooms, colonial patio, wine tours and Andes adventures.';
$_u = $seo['url']         ?? 'https://hostelplaza.com.ar/';
$_i = $seo['image']       ?? 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=';
$_p = $seo['type']        ?? 'website';
$_sc = $seo['schema']     ?? null;
$_h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<title><?php echo $_h($_t); ?></title>
<meta name="description" content="<?php echo $_h($_d); ?>">
<link rel="canonical" href="<?php echo $_h($_u); ?>">
<link rel="icon" href="/iconwhite.ico" sizes="any">
<link rel="apple-touch-icon" sizes="180x180"       href="/H.png">
<meta name="robots" content="max-snippet:160, max-image-preview:large, max-video-preview:-1">

<!-- Site verification -->
<meta name="facebook-domain-verification" content="w6skewvhurx0cx89373n97sfm23y46" />

<!-- Open Graph -->
<meta property="og:type"        content="<?php echo $_h($_p); ?>">
<meta property="og:site_name"   content="Hostel Plaza Mendoza">
<meta property="og:title"       content="<?php echo $_h($_t); ?>">
<meta property="og:description" content="<?php echo $_h($_d); ?>">
<meta property="og:image"       content="<?php echo $_h($_i); ?>">
<meta property="og:url"         content="<?php echo $_h($_u); ?>">
<meta property="og:locale"      content="en_US">

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?php echo $_h($_t); ?>">
<meta name="twitter:description" content="<?php echo $_h($_d); ?>">
<meta name="twitter:image"       content="<?php echo $_h($_i); ?>">

<?php if ($_sc): ?>
<script type="application/ld+json">
<?php echo json_encode($_sc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>
<?php endif; ?>
