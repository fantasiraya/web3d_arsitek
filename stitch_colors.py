#!/usr/bin/env python3
"""
Convert AppSidebar and AppHeader colors to match Stitch design system exactly.
Light mode colors from Stitch:
- Surface (Sidebar/Header): #FFFFFF
- Border: #E2E8F0
- Text: #0F172A
- Text secondary: #475569
"""

import re
import sys

def convert_to_stitch_colors(content, component_name):
    """Convert colors to Stitch design system"""
    
    replacements = [
        # Backgrounds - white in light mode
        (r'bg-white/95 dark:bg-\[#0d0e13\]/90', 'bg-white dark:bg-[#0d0e13]/90'),
        (r'bg-gray-100 dark:bg-\[#13141a\]', 'bg-white dark:bg-[#13141a]'),
        (r'bg-gray-50 dark:bg-\[#13141a\]', 'bg-white dark:bg-[#13141a]'),
        (r'bg-slate-50 dark:bg-\[#13141a\]', 'bg-white dark:bg-[#13141a]'),
        
        # Borders - #E2E8F0 in light mode
        (r'border-gray-200 dark:border-white/5', 'border-[#E2E8F0] dark:border-white/5'),
        (r'border-gray-200 dark:border-white/10', 'border-[#E2E8F0] dark:border-white/10'),
        (r'border-slate-200 dark:border-white/5', 'border-[#E2E8F0] dark:border-white/5'),
        
        # Text colors - #0F172A for primary
        (r'text-gray-900 dark:text-white', 'text-[#0F172A] dark:text-white'),
        (r'text-slate-900 dark:text-white', 'text-[#0F172A] dark:text-white'),
        (r'text-gray-900 dark:text-\[#f3f4f6\]', 'text-[#0F172A] dark:text-[#f3f4f6]'),
        
        # Text secondary - #475569
        (r'text-gray-500 dark:text-\[#6b7280\]', 'text-[#475569] dark:text-[#6b7280]'),
        (r'text-gray-600 dark:text-\[#6b7280\]', 'text-[#475569] dark:text-[#6b7280]'),
        (r'text-slate-500 dark:text-\[#6b7280\]', 'text-[#475569] dark:text-[#6b7280]'),
        (r'text-slate-600 dark:text-\[#6b7280\]', 'text-[#475569] dark:text-[#6b7280]'),
        
        # Hover states
        (r'hover:bg-gray-200 dark:hover:bg-white/5', 'hover:bg-gray-100 dark:hover:bg-white/5'),
        (r'hover:bg-slate-100 dark:hover:bg-white/5', 'hover:bg-gray-100 dark:hover:bg-white/5'),
    ]
    
    for pattern, replacement in replacements:
        content = re.sub(pattern, replacement, content)
    
    print(f"✅ Converted {component_name}")
    return content

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: python3 stitch_colors.py <file_path>")
        sys.exit(1)
    
    file_path = sys.argv[1]
    component_name = file_path.split('/')[-1]
    
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    converted = convert_to_stitch_colors(content, component_name)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(converted)
    
    print(f"✅ Successfully updated {file_path}")
