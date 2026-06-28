
BRAND ATTRIBUTES
brand_private_key: our internal key
Brand Name: examples include Qualtex, Sempertex, Kalisan, Elitex, TufTex, Cattex, Anagram
Brand Description
Brand URL_1
Brand URL_2
Brand Logo URL: (use if no path present)
Brand Logo Path
Brand Abbreviation
Brand Primary Color
Brand Secondary Color
gs1_prefixes (big companies have multiples)
Brand Sort Order
Brand Active
(soft delete, modified)

MATERIALS ATTRIBUTES
material_id: our internal key
material_name: examples include Latex, Foil, Plastic, Chloroprene, Other
material_description: sometimes null
material_url: usually null
material_image_path: usually null
sort_order
logging
soft_delete_tables

COLORS ATTRIBUTES
color_id: our internal key
brand_id: select only 1 from ‘Brands’ table
material_id: select only 1 from ‘Materials’ table. Default is whatever id maps to ‘Latex’
color_name: Given name from the brand. Examples include Onyx Black, Fashion Red, Silk Matte Peach, Diamond Clear
color_code: the brand’s internal color code. Can be empty
hex_color
pms_value
color_family_id: select only 1 from ‘Color-Families’ table
texture_id: select only 1 from ‘Textures’ table
single_image_file_path (can be empty)
cluster_image_file_path (can be empty)
sort_order
logging
soft_delete_tables

COLOR-FAMILIES ATTRIBUTES
color_family_id: our internal key
material_id: select only 1 from ‘Materials’ table. Default is whatever id maps to ‘Latex’
color_family_name: examples include Reds, Blues, Greens, Pinks, Browns, Purples, Yellows, Oranges, Golds
color_family_description
hex_color_start
hex_color_end
single_image_file_path
cluster_image_file_path
sort_order
logging
soft_delete_tables

TEXTURES ATTRIBUTES
texture_id: our internal key
material_id: select only 1 from ‘Materials’ table. Default is whatever id maps to ‘Latex’
brand_id: select only 1 from ‘Brands’ table
texture_name: examples include Crystal, Standard, Silk, Pearl, Neon, Matte, Shiny, Chrome, Reflex
texture_description: 
texture_family_id: select only 1 id from texture-families table
texture_sort_order
texture_image_path. If null, show the texture_family image
sort order/logging/soft delete

TEXTURE-FAMILIES ATTRIBUTES
texture_family_id: our internal key
texture_family_name: examples include Standard, Crystal, Metallic, Neon, Chrome
texture_family_description
texture_family_sort_order
texture_family_image_path
sort order/logging/soft delete


PRICE CODES ATTRIBUTES
price_code_private_key
brand_id: select only 1 from ‘Brands’ table
price_code: exampls  jw, qft
sort order/logging/soft delete
	Note that the actual price will be set per user when they compute their local price.


SHAPES ATTRIBUTES
shape_id: our internal key
material_id: select only 1 from ‘Materials’ table. Default is whatever id maps to ‘Latex’
latex_shape_name: Examples are Round, Link, Cylinder, Heart, Circle, Other
latex_shape_image_path: usually null
sort order/logging/soft delete

THEMES ATTRIBUTES
theme_id: our internal key
theme_name: examples include Holiday, Star Wars, Christmas, Religious, Graduation, Wedding, Birthday, Jungle, Halloween, Spring
theme_description
sort order/logging/soft delete

BALLOON-SIZES ATTRIBUTES
balloon_size_id: our internal key
material_id: select only 1 from ‘Materials’ table
brand_id: select only 1  from ‘Brand’ table
size_id: select only 1  from ‘Sizes’ table
balloon_size_name: example include 5-inch, 11-inch, 12-inch/30cm, 17-inch, 18-inch, 24-inch, 36-inch, 160, 260, 350, 660
balloon_size_description: usually empty
balloon_size_single_image_file_path (can be empty)
balloon_size_cluster_image_file_path (can be empty)
sort order/logging/soft delete


SIZES, aka SIZE-FAMILY
size_id: our internal key
size: examples are Small, Medium, Large, Jumbo, Super Jumbo
size_single_image_file_path (can be empty)
size_cluster_image_file_path (can be empty)
sort order/logging/soft delete
