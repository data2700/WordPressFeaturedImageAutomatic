# WordPressFeaturedImageAutomatic
Combined Featured Image Setter Description: Check for posts every four hours without a featured image, sets them to draft, analyzes the word count, and sets a relevant featured image from Pixabay. Version: 1.0

### Combined Featured Image Setter (WordPress Plugin)

**Description:**  
This WordPress plugin automates the process of setting featured images for posts that lack them. It operates based on the following logic:

1. **Admin Menu Integration:**  
   - Adds a new menu item in the WordPress admin dashboard titled "Featured Image Setter".
   - Provides an option to manually trigger the function to check and update posts.

2. **Scheduled Task:**  
   - On plugin activation, a scheduled task is set to run every four hours.
   - The task checks for published posts without a featured image.

3. **Post Analysis and Update:**  
   - Posts without a featured image are temporarily set to 'draft' status.
   - The content of the post is analyzed to determine the most frequently occurring word (excluding common stop words).
   - This word is then used as a query term to fetch a relevant image from Pixabay.

4. **Pixabay Integration:**  
   - Uses the Pixabay API to fetch an image based on the determined keyword.
   - A random image from the search results is set as the post's featured image.

5. **Logging:**  
   - Errors, such as API failures or issues with image downloading, are logged to a file (`pixabay_log.txt`).

6. **Deactivation Cleanup:**  
   - On plugin deactivation, the scheduled task is removed to prevent unnecessary runs.

**Note:** Users need to provide their Pixabay API key in the `PIXABAY_API_KEY` constant for the plugin to function correctly.
