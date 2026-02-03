# Sidebar Widgets

This directory contains individual widget components for the dynamic sidebar system.

## How to Add a New Sidebar Section

### 1. Backend (Laravel)

1. **Add to Database Seeder**
   Edit `database/seeders/SidebarSectionSeeder.php` and add your new section:

   ```php
   [
       'key' => 'your_section_key',
       'title' => 'Your Section Title',
       'is_visible' => true,
       'sort_order' => 4, // Next available order
   ],
   ```

2. **Run Seeder**
   ```bash
   php artisan db:seed --class=SidebarSectionSeeder
   ```

### 2. Frontend (Vue)

1. **Create Widget Component**
   Create a new Vue component in this directory, e.g., `YourSectionWidget.vue`:

   ```vue
   <template>
     <section class="card panel">
       <div class="panelTitle">{{ title }}</div>
       <!-- Your widget content here -->
     </section>
   </template>

   <script>
   export default {
     name: 'YourSectionWidget',
     props: {
       title: {
         type: String,
         default: 'Your Section Title'
       }
     },
     setup() {
       // Your widget logic here
       return {}
     }
   }
   </script>

   <style scoped>
   /* Your widget styles here */
   </style>
   ```

2. **Update DynamicSidebar Component**
   Edit `../DynamicSidebar.vue`:

   - Import your new component:
     ```js
     import YourSectionWidget from './widgets/YourSectionWidget.vue'
     ```

   - Add to components:
     ```js
     components: {
       NextEventWidget,
       LatestArticlesWidget,
       NasaApodWidget,
       YourSectionWidget // Add this
     }
     ```

   - Add to widget mapper:
     ```js
     const widgetComponents = {
       'next_event': 'NextEventWidget',
       'latest_articles': 'LatestArticlesWidget', 
       'nasa_apod': 'NasaApodWidget',
       'your_section_key': 'YourSectionWidget' // Add this
     }
     ```

### 3. Testing

1. **Admin UI**: Visit `/admin/sidebar` to configure your new section
2. **Frontend**: The widget should appear on the home page feed if enabled
3. **Mobile**: Verify responsive behavior works correctly

## Existing Widgets

### NextEventWidget
- **Key**: `next_event`
- **Purpose**: Displays the next upcoming astronomical event
- **API**: Uses `/events/next` endpoint

### LatestArticlesWidget
- **Key**: `latest_articles`
- **Purpose**: Shows the latest blog articles
- **API**: Uses blog posts service

### NasaApodWidget
- **Key**: `nasa_apod`
- **Purpose**: Displays NASA Image of the Day
- **API**: Uses `/nasa/iotd` endpoint
- **Feature Flag**: Controlled by `VITE_FEATURE_NASA_IOTD`

## Widget Guidelines

1. **Consistent Styling**: Use the provided CSS classes (`card`, `panel`, `panelTitle`, etc.)
2. **Loading States**: Include skeleton loading states for better UX
3. **Error Handling**: Handle API errors gracefully with fallback UI
4. **Props**: Accept a `title` prop to allow customization from admin config
5. **Responsive**: Ensure widgets work well on desktop (sidebar is hidden on mobile)
6. **Performance**: Lazy load data and avoid unnecessary API calls

## Mobile Responsiveness

The sidebar is completely hidden on mobile devices (< 1024px) to save space and improve performance. Widgets should not make API calls on mobile - this is handled automatically by the `DynamicSidebar` component.
