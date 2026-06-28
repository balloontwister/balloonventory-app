Attributes of a bag of balloons


private_key: what balloonventory uses internally to track the item
upc: UPC Code what the manufacturer prints on the bag for inventory i.e. 030625530125. If null, value =  "na”
ean: often the same as the UPC. An alternate to UPC
asin: unique id for selling on Amazon.com. Not currently used, but could be important in the near future.
mfg_no:  Manufacturers internal number for the item. I do not have this number, so it will usually be blank.	
sku: used by warehouses to track the item. Usually last part of the UPC code. i.e. 53012. Not always unique. Could be computed from UPC.
identical_skus: items in the system that are the same product, but in a different bag
gs1_prefix: identifies the manufacturer. Usually first part of the UPC code and linked to a Brand. i.e. 719784. Could be computed from UPC.
brand_id: select only 1 Brand from the 'Brands' table
material_id: select only 1 Material from the 'Materials' table.  i.e. latex
name: name of the bag, given by the source. i.e. "Yellow 260T"
computed_name = a more descriptive name that we generate. 'size'+'color'+'brand'+'shape'+'qty'
description = often null. 
balloon_size_id: select only 1 Size from the ‘Balloon-Sizes' table. i.e. 260 or 12-inch
color_id: select only 1 Color from the 'Colors' table.
texture_id: select only 1 Texture from the 'Textures' table. i.e. Standard, or Shiny.
shape_id: select only 1 Shape from the 'Shapes' table. Usually 'Round' or 'Cylinder'
bag_qty: the quantity of items in the bag. Usually 50 or 100
packaging: the style of packaging. Examples include loose. Nozzle Up, retail.
single_image_file_path: defaults to color's path when null.
cluster_image_file_path: defaults to color's path when null.
printed = true or false. Default is false.
print_colors = a list of what color the ink is
print_sides = a list of where the ink is on the balloon. Examples are Top, Side, Two-Sides, Four-Sides, Five-Sides
price_code: select only 1 Price Code from the 'Price Codes' table
theme_id: a list of of Theme items from the ‘Themes’ table. Default is ’null’
status = Boolean. active or inactive. Default is active.
discontinued_at = null (only applies when it's inactive)
product_version = usually null. Or should we assume 1. It's a only a new version when the UPC is reused or the product is changed.
Soft delete and logging columns, such as:
	created_at:
	updated_at:
	updated_by:
	deleted

	

