import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AccesosRapidos } from './accesos-rapidos';

describe('AccesosRapidos', () => {
  let component: AccesosRapidos;
  let fixture: ComponentFixture<AccesosRapidos>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AccesosRapidos],
    }).compileComponents();

    fixture = TestBed.createComponent(AccesosRapidos);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
