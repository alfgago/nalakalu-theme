# Nalakalu Design System

## Typography

### Font Family

- **Primary**: Fraunces 72pt (Google Fonts)
- **Weights**: 300 (Light), 400 (Regular)

### Font Sizes (rem-based, 1rem = 10px)

#### Display Heading

```css
.font-heading-display
```

- **Size**: 12.8rem (128px)
- **Weight**: 400 (Regular)
- **Line Height**: normal
- **Transform**: uppercase
- **Mobile**: 8rem (80px)
- **Small Mobile**: 6.4rem (64px)

#### Heading 1

```css
.font-heading-1
```

- **Size**: 8.4rem (84px)
- **Weight**: 300 (Light)
- **Line Height**: 9.6rem (96px)
- **Transform**: uppercase
- **Mobile**: 5.6rem (56px)
- **Small Mobile**: 4.8rem (48px)

#### Heading 2

```css
.font-heading-2
```

- **Size**: 6.4rem (64px)
- **Weight**: 300 (Light)
- **Line Height**: 8.4rem (84px)
- **Mobile**: 4.8rem (48px)
- **Small Mobile**: 3.6rem (36px)

## Color Palette

### Primary Colors

- **Brown**: `#3D332B` (Default text color)
- **Black**: `#000000`
- **White**: `#FFFFFF`
- **Beige**: `#D7C5B4`

### Utility Classes

```css
/* Text Colors */
.text-brown    /* #3D332B */
/* #3D332B */
.text-beige    /* #D7C5B4 */
.text-black    /* #000000 */
.text-white    /* #FFFFFF */

/* Background Colors */
.bg-brown      /* #3D332B */
.bg-beige      /* #D7C5B4 */
.bg-black      /* #000000 */
.bg-white      /* #FFFFFF */

/* Border Colors */
.border-brown  /* #3D332B */
.border-beige  /* #D7C5B4 */
.border-black  /* #000000 */
.border-white; /* #FFFFFF */
```

## Usage Examples

### Hero Block

```html
<h1 class="font-heading-1 text-white">Hero Title</h1>
<p class="text-xl text-white">Hero description</p>
```

### Section Headings

```html
<h2 class="font-heading-2 text-brown">Section Title</h2>
```

### Display Text

```html
<h1 class="font-heading-display text-brown">Display Title</h1>
```

## Responsive Design

All font sizes automatically scale down on mobile devices:

- **Tablet (768px and below)**: ~30% reduction
- **Mobile (480px and below)**: ~50% reduction

## Implementation

The design system is implemented through:

1. **Custom CSS utilities** in `assets/css/custom-utilities.css`
2. **Tailwind config** in `tailwind.config.js`
3. **Google Fonts** integration for Fraunces
4. **Responsive breakpoints** for mobile optimization

## File Structure

```
assets/
├── css/
│   └── custom-utilities.css
tailwind.config.js
DESIGN_SYSTEM.md
```
