import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AgendaMunicipal } from './agenda-municipal';

describe('AgendaMunicipal', () => {
  let component: AgendaMunicipal;
  let fixture: ComponentFixture<AgendaMunicipal>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AgendaMunicipal],
    }).compileComponents();

    fixture = TestBed.createComponent(AgendaMunicipal);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
