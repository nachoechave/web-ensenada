import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-portal-icon',
  template: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      @switch (name) {
        @case ('coins') { <ellipse cx="12" cy="6" rx="7" ry="3"></ellipse><path d="M5 6v4c0 1.7 3.1 3 7 3s7-1.3 7-3V6"></path><path d="M5 10v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4"></path><path d="M5 14v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4"></path> }
        @case ('calendar') { <rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path><path d="M8 14h3v3H8z"></path> }
        @case ('building') { <path d="M3 21h18M5 21V10M9 21V10M15 21V10M19 21V10M3 10h18L12 3 3 10z"></path> }
        @case ('recycle') { <path d="M8.5 4 11 2l2.5 4"></path><path d="M10.8 3.2H8.4a3 3 0 0 0-2.6 1.5L4 8"></path><path d="m19 9 2 2.5-4 2.5"></path><path d="M20 11.5 18.8 14a3 3 0 0 1-2.7 1.7H12"></path><path d="m9 20-3.5.5.5-4.5"></path><path d="M6 19.5 4.6 17a3 3 0 0 1 0-3l1.3-2.2"></path> }
        @case ('users') { <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path> }
        @case ('providers') { <circle cx="8" cy="8" r="3"></circle><circle cx="16.5" cy="9" r="2.5"></circle><path d="M2.5 20v-1.2A5.8 5.8 0 0 1 8.3 13h.4a5.8 5.8 0 0 1 5.8 5.8V20"></path><path d="M14 14.2a4.8 4.8 0 0 1 7.5 4V20"></path> }
        @case ('hardhat') { <path d="M4 17h16"></path><path d="M6 17v-2a6 6 0 0 1 12 0v2"></path><path d="M9 15V8h6v7M12 8V5"></path><path d="M3 17h18v3H3z"></path> }
        @case ('heart') { <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path> }
        @case ('ball') { <circle cx="12" cy="12" r="9"></circle><path d="m8.5 9 3.5-2.5L15.5 9l-1.3 4.2h-4.4L8.5 9zM12 6.5V3M8.5 9 5 7M15.5 9 19 7M9.8 13.2 7.5 17M14.2 13.2l2.3 3.8"></path> }
        @case ('culture') { <path d="M3 5h8v10H3zM13 9h8v10h-8z"></path><path d="M5 8h4M15 12h4"></path><path d="M5 12c1 1 2 1 3 0M15 16c1 1 2 1 3 0"></path> }
        @case ('receipt') { <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"></path><path d="M9 8h6M9 12h6M9 16h4"></path> }
        @case ('invoice') { <path d="M6 2h8l4 4v16H6z"></path><path d="M14 2v5h5M9 12h6M9 16h6"></path><path d="M9 9h2"></path> }
        @case ('mail') { <rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path> }
        @default { <path d="M6 2h8l4 4v16H6z"></path><path d="M14 2v5h5M9 12h6M9 16h6"></path> }
      }
    </svg>
  `,
  styles: [`:host{display:inline-grid;place-items:center;width:1.6em;height:1.6em}svg{width:100%;height:100%;display:block}`],
})
export class PortalIcon {
  @Input() name = 'document';
}
