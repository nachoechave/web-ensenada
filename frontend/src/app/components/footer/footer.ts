import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { siteContent } from '../../config/site-content';
import { externalLinks } from '../../config/external-links';
@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.css',
})
export class Footer {
  readonly contenido = siteContent;
  readonly externalLinks = externalLinks;
}
